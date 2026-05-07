#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import re
import unicodedata
from io import BytesIO
from pathlib import Path
from typing import Dict, List

from pypdf import PdfReader, PdfWriter
from reportlab.lib.pagesizes import letter
from reportlab.lib.units import inch
from reportlab.pdfgen import canvas


def sanitize_name(value: str) -> str:
    value = value.replace("ñ", "n").replace("Ñ", "N")
    value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode("ascii")
    value = re.sub(r"[^A-Za-z0-9 _.-]+", "", value)
    value = re.sub(r"\s+", "_", value.strip())
    value = value.strip("._-")
    return value or "archivo"


def create_cover_pdf(title: str, files: List[Dict[str, str]], logo_path: str | None = None) -> bytes:
    buffer = BytesIO()
    c = canvas.Canvas(buffer, pagesize=letter)
    width, height = letter

    y = height - 1.0 * inch
    if logo_path:
        logo = Path(logo_path)
        if logo.exists():
            try:
                # 2.5x respecto al tamaño previo (1.8" x 0.8")
                logo_w = 4.5 * inch
                logo_h = 2.0 * inch
                logo_x = (width - logo_w) / 2.0
                logo_y = height - 0.8 * inch - logo_h
                c.drawImage(str(logo), logo_x, logo_y, width=logo_w, height=logo_h, preserveAspectRatio=True, mask="auto")
                y = logo_y - (0.25 * inch)
            except Exception:
                pass

    c.setFont("Helvetica-Bold", 14)
    c.drawString(0.8 * inch, y, f"Adjuntos para: {title}")
    y -= 0.35 * inch
    c.setFont("Helvetica", 10)
    c.drawString(0.8 * inch, y, "Listado de adjuntos:")
    y -= 0.25 * inch

    if not files:
        c.drawString(1.0 * inch, y, "(ninguno)")
    else:
        for item in files:
            if y < 1.0 * inch:
                c.showPage()
                y = height - 1.0 * inch
                c.setFont("Helvetica", 10)
            c.drawString(1.0 * inch, y, item["name"])
            y -= 0.2 * inch

    c.showPage()
    c.save()
    buffer.seek(0)
    return buffer.read()


def build_pdf_with_attachments(cover_pdf: bytes, files: List[Dict[str, str]], output_pdf: Path) -> List[str]:
    writer = PdfWriter()
    reader = PdfReader(BytesIO(cover_pdf))
    for page in reader.pages:
        writer.add_page(page)

    failed: List[str] = []
    for item in files:
        path = Path(item["path"])
        if not path.exists():
            failed.append(item["name"])
            continue
        try:
            with path.open("rb") as fh:
                writer.add_attachment(item["name"], fh.read())
        except Exception:
            failed.append(item["name"])

    with output_pdf.open("wb") as fh:
        writer.write(fh)
    return failed


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", required=True)
    args = parser.parse_args()

    manifest_path = Path(args.manifest)
    payload = json.loads(manifest_path.read_text(encoding="utf-8"))
    version_number = int(payload.get("version_number") or 1)
    output_dir = Path(payload.get("output_dir"))
    output_dir.mkdir(parents=True, exist_ok=True)
    logo_path = payload.get("logo_path")

    documents = payload.get("documents") or []
    generated: List[str] = []
    generated_set = set()
    missing_lines: List[str] = []
    general_lines: List[str] = []
    missing_count = 0

    for doc in documents:
        title = str(doc.get("title") or "Documento")
        # Priorizar el titulo para el nombre final del PDF (estable y legible)
        base_name = sanitize_name(title)
        if not base_name:
            base_name = sanitize_name(str(doc.get("base_name") or title))
        files = doc.get("files") or []
        output_name = f"{base_name}_V{version_number}.pdf"
        output_name = sanitize_name(output_name[:-4]) + ".pdf"
        original_output_name = output_name
        suffix = 2
        while output_name.lower() in generated_set:
            output_name = sanitize_name(original_output_name[:-4]) + f"_{suffix}.pdf"
            suffix += 1
        generated_set.add(output_name.lower())
        output_pdf = output_dir / output_name

        cover = create_cover_pdf(title, files, logo_path)
        failed = build_pdf_with_attachments(cover, files, output_pdf)
        generated.append(output_name)

        general_lines.append(f"== {title} ==")
        if files:
            for item in files:
                general_lines.append(item["name"])
        else:
            general_lines.append("(sin adjuntos)")
        general_lines.append("")

        missing_lines.append(f"== {title} ==")
        if failed:
            missing_count += len(failed)
            missing_lines.extend(failed)
        else:
            missing_lines.append("(ninguno)")
        missing_lines.append("")

    missing_report = output_dir / "reporte_faltantes.txt"
    general_report = output_dir / "reporte_general.txt"
    missing_report.write_text("\n".join(missing_lines).rstrip() + "\n", encoding="utf-8")
    general_report.write_text("\n".join(general_lines).rstrip() + "\n", encoding="utf-8")

    print(json.dumps({
        "version_number": version_number,
        "pdf_filenames": generated,
        "missing_report": str(missing_report),
        "general_report": str(general_report),
        "missing_count": missing_count,
    }, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
