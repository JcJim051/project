#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import re
import unicodedata
from io import BytesIO
from pathlib import Path
from typing import Dict, List, Tuple

from pypdf import PdfReader, PdfWriter
from reportlab.lib.pagesizes import letter
from reportlab.lib.units import inch
from reportlab.pdfgen import canvas
from reportlab.pdfbase import pdfmetrics


def sanitize_name(value: str) -> str:
    value = value.replace("ñ", "n").replace("Ñ", "N")
    value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode("ascii")
    value = value.replace(".", " ").replace("_", " ")
    value = re.sub(r"[^A-Za-z0-9 -]+", "", value)
    value = re.sub(r"\s+", " ", value.strip())
    value = value.strip(" -")
    return value or "archivo"


def ordered_manifest_files(files: List[Dict[str, str]]) -> List[Dict[str, str]]:
    return sorted(
        files,
        key=lambda item: (
            str(item.get("sort_key", "")).lower(),
            str(item.get("name", "")).lower(),
        ),
    )


def grouped_manifest_files(files: List[Dict[str, str]]) -> List[Tuple[str, List[Dict[str, str]]]]:
    groups: Dict[str, List[Dict[str, str]]] = {}
    order: Dict[str, str] = {}
    for item in ordered_manifest_files(files):
        folder = str(item.get("folder_name") or "Sin carpeta").strip() or "Sin carpeta"
        key = folder.lower()
        groups.setdefault(key, [])
        order.setdefault(key, folder)

        bundle_files = item.get("bundle_files") or []
        if bundle_files:
            groups[key].extend(ordered_manifest_files(list(bundle_files)))
        else:
            groups[key].append(item)

    return sorted(
        [(order[key], groups[key]) for key in groups],
        key=lambda pair: sanitize_name(pair[0]).lower(),
    )


def attachment_display_name(item: Dict[str, str]) -> str:
    return str(item.get("name") or "archivo")


def create_cover_pdf(title: str, files: List[Dict[str, str]], logo_path: str | None = None) -> bytes:
    buffer = BytesIO()
    c = canvas.Canvas(buffer, pagesize=letter)
    width, height = letter

    top_margin = 0.6 * inch
    side_margin = 0.8 * inch
    bottom_margin = 0.8 * inch
    header_height = 0.0
    font_name = "Helvetica"

    def wrap_text(text: str, font: str, size: int, max_width: float) -> List[str]:
        words = text.split()
        lines: List[str] = []
        current = ""
        for word in words:
            candidate = f"{current} {word}".strip()
            if pdfmetrics.stringWidth(candidate, font, size) <= max_width:
                current = candidate
            else:
                if current:
                    lines.append(current)
                current = word
        if current:
            lines.append(current)
        return lines

    def draw_clip_icon(x: float, y: float) -> None:
        # Vector paperclip icon, avoids emoji/font rendering issues.
        c.saveState()
        c.translate(x, y)
        c.rotate(-28)
        c.setLineWidth(1.2)
        c.roundRect(0, 0, 7, 15, 3.5, stroke=1, fill=0)
        c.roundRect(1.8, 3, 3.5, 8.5, 1.8, stroke=1, fill=0)
        c.restoreState()

    def draw_logo() -> float:
        nonlocal header_height
        header_height = 0.0
        if not logo_path:
            return header_height
        logo = Path(logo_path)
        if not logo.exists():
            return header_height
        try:
            # 10% larger than the previous 4.5" x 2.0" logo size.
            logo_w = 4.95 * inch
            logo_h = 2.2 * inch
            logo_x = (width - logo_w) / 2.0
            logo_y = height - top_margin - logo_h
            c.drawImage(str(logo), logo_x, logo_y, width=logo_w, height=logo_h, preserveAspectRatio=True, mask="auto")
            header_height = logo_h + 0.25 * inch
        except Exception:
            header_height = 0.0
        return header_height

    draw_logo()
    y = height - top_margin - header_height

    c.setFont("Helvetica-Bold", 14)
    c.drawString(side_margin, y, f"Adjuntos para: {title}")
    y -= 0.45 * inch
    c.setFont(font_name, 10)
    c.drawString(side_margin, y, "Listado de adjuntos:")
    y -= 0.25 * inch

    ordered_files = ordered_manifest_files(files)
    if not ordered_files:
        c.drawString(side_margin + 0.2 * inch, y, "(ninguno)")
        y -= 0.22 * inch
    else:
        for folder, folder_files in grouped_manifest_files(files):
            if y < bottom_margin + 0.55 * inch:
                c.showPage()
                draw_logo()
                y = height - top_margin - header_height
                c.setFont(font_name, 10)
            c.setFont("Helvetica-Bold", 10)
            c.drawString(side_margin + 0.05 * inch, y, folder)
            y -= 0.2 * inch
            c.setFont(font_name, 10)
            for item in folder_files:
                if y < bottom_margin + 0.4 * inch:
                    c.showPage()
                    draw_logo()
                    y = height - top_margin - header_height
                    c.setFont("Helvetica-Bold", 10)
                    c.drawString(side_margin + 0.05 * inch, y, folder)
                    y -= 0.2 * inch
                    c.setFont(font_name, 10)
                c.drawString(side_margin + 0.25 * inch, y, item["name"])
                y -= 0.18 * inch
            y -= 0.06 * inch

    note_text = (
        "Nota: Los documentos relacionados en este listado están adjuntos dentro de este PDF. "
        "Para visualizarlos, ubique la sección de adjuntos en su visor (ícono de clip)."
    )
    note_font_size = 10
    note_text_x = side_margin + 0.35 * inch
    note_max_width = width - note_text_x - side_margin
    note_lines = wrap_text(note_text, font_name, note_font_size, note_max_width)
    note_line_height = 0.2 * inch
    note_required_height = (len(note_lines) * note_line_height) + (0.2 * inch)

    if y < bottom_margin + 0.4 * inch + note_required_height:
        c.showPage()
        draw_logo()
        y = height - top_margin - header_height

    y -= 0.2 * inch
    c.setFont(font_name, note_font_size)
    draw_clip_icon(side_margin, y - 0.08 * inch)
    for line in note_lines:
        c.drawString(note_text_x, y, line)
        y -= note_line_height

    c.showPage()
    c.save()
    buffer.seek(0)
    return buffer.read()


def build_pdf_bytes_with_attachments(
    title: str,
    files: List[Dict[str, str]],
    logo_path: str | None = None,
) -> Tuple[bytes, List[str]]:
    writer = PdfWriter()
    reader = PdfReader(BytesIO(create_cover_pdf(title, files, logo_path)))
    for page in reader.pages:
        writer.add_page(page)

    failed: List[str] = []
    for item in files:
        try:
            if item.get("bundle_files"):
                bundle_title = str(item.get("bundle_title") or Path(str(item["name"])).stem)
                bundle_bytes, nested_failed = build_pdf_bytes_with_attachments(
                    bundle_title,
                    list(item.get("bundle_files") or []),
                    logo_path,
                )
                writer.add_attachment(attachment_display_name(item), bundle_bytes)
                failed.extend([f"{item['name']} -> {name}" for name in nested_failed])
                continue

            path = Path(item["path"])
            if not path.exists():
                failed.append(str(item["name"]))
                continue

            with path.open("rb") as fh:
                writer.add_attachment(attachment_display_name(item), fh.read())
        except Exception:
            failed.append(str(item["name"]))

    output = BytesIO()
    writer.write(output)
    output.seek(0)
    return output.read(), failed


def build_pdf_with_attachments(title: str, files: List[Dict[str, str]], output_pdf: Path, logo_path: str | None = None) -> List[str]:
    pdf_bytes, failed = build_pdf_bytes_with_attachments(title, files, logo_path)
    with output_pdf.open("wb") as fh:
        fh.write(pdf_bytes)
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
        files = ordered_manifest_files(list(doc.get("files") or []))
        output_name = f"{base_name}_V{version_number}.pdf"
        output_name = sanitize_name(output_name[:-4]) + ".pdf"
        original_output_name = output_name
        suffix = 2
        while output_name.lower() in generated_set:
            output_name = sanitize_name(original_output_name[:-4]) + f"_{suffix}.pdf"
            suffix += 1
        generated_set.add(output_name.lower())
        output_pdf = output_dir / output_name

        failed = build_pdf_with_attachments(title, files, output_pdf, logo_path)
        generated.append(output_name)

        general_lines.append(f"== {title} ==")
        if files:
            for folder, folder_files in grouped_manifest_files(files):
                general_lines.append(f"[{folder}]")
                for item in folder_files:
                    general_lines.append(f"  {item['name']}")
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
