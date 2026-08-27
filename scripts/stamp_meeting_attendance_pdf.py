from __future__ import annotations

import io
import sys
from pathlib import Path

try:
    from pypdf import PdfReader, PdfWriter
except ImportError:
    from PyPDF2 import PdfReader, PdfWriter
from reportlab.lib.utils import ImageReader
from reportlab.pdfgen import canvas


def build_overlay(page_width: float, page_height: float, header_path: str, header_width: float, header_height: float, footer_path: str, footer_width: float, footer_height: float) -> bytes:
    buffer = io.BytesIO()
    pdf = canvas.Canvas(buffer, pagesize=(page_width, page_height))

    left_margin = 72.0
    header_y = page_height - 78.0
    footer_y = 18.0

    if header_path:
        pdf.drawImage(ImageReader(header_path), left_margin, header_y, width=header_width, height=header_height, mask="auto")

    if footer_path:
        pdf.drawImage(ImageReader(footer_path), left_margin, footer_y, width=footer_width, height=footer_height, mask="auto")

    pdf.save()
    buffer.seek(0)
    return buffer.read()


def main() -> int:
    if len(sys.argv) != 9:
        raise SystemExit("Usage: stamp_meeting_attendance_pdf.py input_pdf output_pdf header_img header_w header_h footer_img footer_w footer_h")

    input_pdf, output_pdf, header_img, header_w, header_h, footer_img, footer_w, footer_h = sys.argv[1:]

    reader = PdfReader(input_pdf)
    writer = PdfWriter()

    for page in reader.pages:
        page_width = float(page.mediabox.width)
        page_height = float(page.mediabox.height)
        overlay_pdf = PdfReader(
            io.BytesIO(
                build_overlay(
                    page_width,
                    page_height,
                    header_img,
                    float(header_w),
                    float(header_h),
                    footer_img,
                    float(footer_w),
                    float(footer_h),
                )
            )
        )
        page.merge_page(overlay_pdf.pages[0])
        writer.add_page(page)

    output_path = Path(output_pdf)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    with output_path.open("wb") as stream:
        writer.write(stream)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
