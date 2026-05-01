from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
import html
import re

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.cidfonts import UnicodeCIDFont
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    HRFlowable,
    ListFlowable,
    ListItem,
    PageBreak,
    PageTemplate,
    Paragraph,
    Preformatted,
    Spacer,
    Table,
    TableStyle,
)
from reportlab.platypus.tableofcontents import TableOfContents


ROOT = Path(__file__).resolve().parents[1]
PAGE_WIDTH, PAGE_HEIGHT = A4
FONT_NAME = "STSong-Light"
COPYRIGHT = "©2026保定学院生化学院党员管理系统"
WATERMARK = "生化学院党员管理系统专属用户手册"

DOCS = [
    ("PROJECT_DOCUMENTATION.md", "PROJECT_DOCUMENTATION.pdf", "项目技术文档"),
    ("USER_MANUAL.md", "USER_MANUAL.pdf", "用户使用手册"),
]


@dataclass
class HeadingEntry:
    level: int
    title: str


def register_fonts() -> None:
    pdfmetrics.registerFont(UnicodeCIDFont(FONT_NAME))


def make_styles():
    styles = getSampleStyleSheet()

    base = ParagraphStyle(
        "BaseCN",
        parent=styles["BodyText"],
        fontName=FONT_NAME,
        fontSize=10.5,
        leading=17,
        textColor=colors.HexColor("#1F2937"),
        spaceAfter=4,
    )
    styles.add(base)

    styles.add(
        ParagraphStyle(
            "DocTitleCN",
            parent=base,
            fontSize=22,
            leading=28,
            alignment=TA_CENTER,
            textColor=colors.HexColor("#991B1B"),
            spaceAfter=16,
        )
    )
    styles.add(
        ParagraphStyle(
            "Heading1CN",
            parent=base,
            fontSize=17,
            leading=24,
            textColor=colors.HexColor("#111827"),
            spaceBefore=16,
            spaceAfter=8,
        )
    )
    styles.add(
        ParagraphStyle(
            "Heading2CN",
            parent=base,
            fontSize=14,
            leading=20,
            textColor=colors.HexColor("#111827"),
            leftIndent=0,
            spaceBefore=12,
            spaceAfter=6,
        )
    )
    styles.add(
        ParagraphStyle(
            "Heading3CN",
            parent=base,
            fontSize=12,
            leading=18,
            textColor=colors.HexColor("#111827"),
            spaceBefore=10,
            spaceAfter=4,
        )
    )
    styles.add(
        ParagraphStyle(
            "CodeCN",
            parent=base,
            fontName="Courier",
            fontSize=9,
            leading=13,
            backColor=colors.HexColor("#F8FAFC"),
            borderColor=colors.HexColor("#E5E7EB"),
            borderWidth=0.6,
            borderPadding=6,
            borderRadius=4,
            leftIndent=6,
            rightIndent=6,
            spaceBefore=4,
            spaceAfter=8,
        )
    )
    styles.add(
        ParagraphStyle(
            "MetaCN",
            parent=base,
            fontSize=9,
            leading=14,
            alignment=TA_RIGHT,
            textColor=colors.HexColor("#6B7280"),
            spaceAfter=12,
        )
    )
    styles.add(
        ParagraphStyle(
            "TOCTitleCN",
            parent=base,
            fontSize=16,
            leading=22,
            alignment=TA_LEFT,
            textColor=colors.HexColor("#991B1B"),
            spaceBefore=4,
            spaceAfter=8,
        )
    )

    toc_level_1 = ParagraphStyle(
        "TOCLevel1CN",
        parent=base,
        fontSize=10.5,
        leading=16,
        leftIndent=8,
        firstLineIndent=0,
        spaceBefore=2,
        textColor=colors.HexColor("#111827"),
    )
    toc_level_2 = ParagraphStyle(
        "TOCLevel2CN",
        parent=base,
        fontSize=9.8,
        leading=15,
        leftIndent=20,
        firstLineIndent=0,
        textColor=colors.HexColor("#374151"),
    )

    return styles, toc_level_1, toc_level_2


class DocTemplate(BaseDocTemplate):
    def __init__(self, filename: str, **kwargs):
        super().__init__(filename, **kwargs)
        frame = Frame(self.leftMargin, self.bottomMargin, self.width, self.height, id="normal")
        template = PageTemplate(id="main", frames=[frame], onPage=self.draw_page)
        self.addPageTemplates([template])
        self._heading_seq = 0

    def beforeDocument(self):
        self._heading_seq = 0

    def afterFlowable(self, flowable):
        style_name = getattr(flowable, "style", None)
        if not style_name:
            return
        style_name = flowable.style.name
        if style_name not in {"Heading1CN", "Heading2CN"}:
            return

        self._heading_seq += 1
        key = f"h{self._heading_seq:04d}"
        text = flowable.getPlainText()
        level = 0 if style_name == "Heading1CN" else 1
        self.canv.bookmarkPage(key)
        self.canv.addOutlineEntry(text, key, level=level, closed=False)
        self.notify("TOCEntry", (level, text, self.page, key))

    def draw_page(self, canvas, doc):
        canvas.saveState()
        canvas.setFont(FONT_NAME, 8.5)
        canvas.setFillColor(colors.HexColor("#6B7280"))
        canvas.drawCentredString(PAGE_WIDTH / 2, PAGE_HEIGHT - 12 * mm, COPYRIGHT)

        canvas.setFillColor(colors.Color(0.72, 0.72, 0.72, alpha=0.09))
        canvas.setFont(FONT_NAME, 18)
        base_y = 35 * mm
        x_positions = [28 * mm, 83 * mm, 138 * mm]
        for row in range(7):
            y = base_y + row * 34 * mm
            for x in x_positions:
                canvas.saveState()
                canvas.translate(x, y)
                canvas.rotate(28)
                canvas.drawString(0, 0, WATERMARK)
                canvas.restoreState()

        canvas.setFillColor(colors.HexColor("#6B7280"))
        canvas.setFont(FONT_NAME, 8.5)
        canvas.drawRightString(PAGE_WIDTH - doc.rightMargin, 10 * mm, str(canvas.getPageNumber()))
        canvas.restoreState()


def inline_markup(text: str) -> str:
    escaped = html.escape(text)
    escaped = re.sub(r"`([^`]+)`", r'<font face="Courier">\1</font>', escaped)
    escaped = re.sub(r"\*\*([^*]+)\*\*", r"<b>\1</b>", escaped)
    escaped = re.sub(r"\*([^*]+)\*", r"<i>\1</i>", escaped)
    escaped = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", r'<a href="\2">\1</a>', escaped)
    return escaped


def is_table_separator(line: str) -> bool:
    stripped = line.strip()
    return bool(stripped) and all(ch in "|:- " for ch in stripped)


def split_table_row(line: str) -> list[str]:
    row = line.strip().strip("|")
    return [cell.strip() for cell in row.split("|")]


def clean_lines(md_text: str) -> list[str]:
    lines = md_text.replace("\r\n", "\n").replace("\r", "\n").split("\n")
    result: list[str] = []
    skip_toc = False
    saw_toc_heading = False

    for line in lines:
        heading_match = re.match(r"^(#{1,3})\s+(.+?)\s*$", line)
        if heading_match and heading_match.group(2).strip() in {"目录", "目 录"}:
            skip_toc = True
            saw_toc_heading = True
            continue

        if skip_toc:
            if line.strip() == "---":
                skip_toc = False
            continue

        if saw_toc_heading and line.strip() == "---":
            saw_toc_heading = False
            continue

        result.append(line)

    return result


def parse_blocks(lines: list[str]) -> list[tuple[str, object]]:
    blocks: list[tuple[str, object]] = []
    i = 0
    in_code = False
    code_lines: list[str] = []

    while i < len(lines):
        line = lines[i]
        stripped = line.strip()

        if stripped.startswith("```") or stripped.startswith("~~~"):
            if in_code:
                blocks.append(("code", "\n".join(code_lines)))
                code_lines = []
                in_code = False
            else:
                in_code = True
            i += 1
            continue

        if in_code:
            code_lines.append(line.rstrip("\n"))
            i += 1
            continue

        if not stripped:
            i += 1
            continue

        if stripped == "---":
            blocks.append(("hr", None))
            i += 1
            continue

        heading = re.match(r"^(#{1,4})\s+(.+?)\s*$", line)
        if heading:
            blocks.append(("heading", (len(heading.group(1)), heading.group(2).strip())))
            i += 1
            continue

        if stripped.startswith("|") and i + 1 < len(lines) and is_table_separator(lines[i + 1]):
            table_lines = [line, lines[i + 1]]
            i += 2
            while i < len(lines) and lines[i].strip().startswith("|"):
                table_lines.append(lines[i])
                i += 1
            blocks.append(("table", table_lines))
            continue

        if re.match(r"^[-*]\s+", stripped):
            items = []
            while i < len(lines) and re.match(r"^[-*]\s+", lines[i].strip()):
                items.append(re.sub(r"^[-*]\s+", "", lines[i].strip()))
                i += 1
            blocks.append(("ul", items))
            continue

        if re.match(r"^\d+\.\s+", stripped):
            items = []
            while i < len(lines) and re.match(r"^\d+\.\s+", lines[i].strip()):
                items.append(re.sub(r"^\d+\.\s+", "", lines[i].strip()))
                i += 1
            blocks.append(("ol", items))
            continue

        para_lines = [stripped]
        i += 1
        while i < len(lines):
            nxt = lines[i].strip()
            if not nxt:
                break
            if (
                nxt.startswith("#")
                or nxt == "---"
                or nxt.startswith("|")
                or nxt.startswith("```")
                or nxt.startswith("~~~")
                or re.match(r"^[-*]\s+", nxt)
                or re.match(r"^\d+\.\s+", nxt)
            ):
                break
            para_lines.append(nxt)
            i += 1
        blocks.append(("p", " ".join(para_lines)))

    return blocks


def build_table(table_lines: list[str], base_style: ParagraphStyle) -> Table:
    rows = [split_table_row(line) for line in table_lines]
    header = rows[0]
    body = rows[2:]
    data = [[Paragraph(inline_markup(cell), base_style) for cell in header]]
    for row in body:
        normalized = row + [""] * (len(header) - len(row))
        data.append([Paragraph(inline_markup(cell), base_style) for cell in normalized[: len(header)]])

    table = Table(data, repeatRows=1)
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#FEF2F2")),
                ("TEXTCOLOR", (0, 0), (-1, 0), colors.HexColor("#111827")),
                ("GRID", (0, 0), (-1, -1), 0.6, colors.HexColor("#E5E7EB")),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 6),
                ("RIGHTPADDING", (0, 0), (-1, -1), 6),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ]
        )
    )
    return table


def build_story(md_path: Path, title: str):
    styles, toc_level_1, toc_level_2 = make_styles()
    toc = TableOfContents()
    toc.levelStyles = [toc_level_1, toc_level_2]
    toc.dotsMinLevel = 0

    story = [
        Paragraph(title, styles["DocTitleCN"]),
        Paragraph("导出时间：2026-04-28", styles["MetaCN"]),
        Paragraph("目录", styles["TOCTitleCN"]),
        toc,
        Spacer(1, 8),
        PageBreak(),
    ]

    lines = clean_lines(md_path.read_text(encoding="utf-8"))
    blocks = parse_blocks(lines)

    for block_type, payload in blocks:
        if block_type == "heading":
            level, text = payload
            if level == 1:
                style = styles["Heading1CN"]
            elif level == 2:
                style = styles["Heading2CN"]
            else:
                style = styles["Heading3CN"]
            story.append(Paragraph(inline_markup(text), style))
            continue

        if block_type == "p":
            story.append(Paragraph(inline_markup(payload), styles["BaseCN"]))
            story.append(Spacer(1, 2))
            continue

        if block_type == "code":
            story.append(Preformatted(payload, styles["CodeCN"]))
            continue

        if block_type == "hr":
            story.append(HRFlowable(width="100%", thickness=0.7, color=colors.HexColor("#E5E7EB")))
            story.append(Spacer(1, 6))
            continue

        if block_type in {"ul", "ol"}:
            bullet_type = "bullet" if block_type == "ul" else "1"
            items = [
                ListItem(Paragraph(inline_markup(item), styles["BaseCN"]), leftIndent=10)
                for item in payload
            ]
            story.append(
                ListFlowable(
                    items,
                    bulletType=bullet_type,
                    start="1",
                    leftIndent=14,
                )
            )
            story.append(Spacer(1, 4))
            continue

        if block_type == "table":
            story.append(build_table(payload, styles["BaseCN"]))
            story.append(Spacer(1, 6))
            continue

    return story


def generate_pdf(md_name: str, pdf_name: str, title: str) -> None:
    md_path = ROOT / md_name
    pdf_path = ROOT / pdf_name
    doc = DocTemplate(
        str(pdf_path),
        pagesize=A4,
        leftMargin=18 * mm,
        rightMargin=18 * mm,
        topMargin=20 * mm,
        bottomMargin=18 * mm,
        title=title,
        author="OpenAI Codex",
    )
    story = build_story(md_path, title)
    doc.multiBuild(story)


def main() -> None:
    register_fonts()
    for md_name, pdf_name, title in DOCS:
        generate_pdf(md_name, pdf_name, title)
        print(f"generated {pdf_name}")


if __name__ == "__main__":
    main()
