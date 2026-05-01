"""
生化学院党员信息管理系统 — 用户使用手册 PDF 生成脚本
将 docs/ 目录下的4份 Markdown 手册转为带可跳转目录的 PDF。

依赖（仅需 reportlab，项目已有）：
    pip install reportlab

运行：
    python scripts/generate_manuals_pdf.py
"""
from __future__ import annotations

import html
import re
from dataclasses import dataclass
from datetime import date
from pathlib import Path

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

# ── 路径与常量 ──────────────────────────────────────────────────────────────
ROOT = Path(__file__).resolve().parents[1]
DOCS_DIR = ROOT / "docs"
PAGE_WIDTH, PAGE_HEIGHT = A4
FONT_NAME = "STSong-Light"
TODAY = date.today().strftime("%Y年%m月%d日")
COPYRIGHT = "© 生化学院党员信息管理系统  版本 v2.0.4"
WATERMARK = "生化学院党员管理系统专属用户手册"

# (Markdown文件名, PDF文件名, 显示标题, 副标题)
MANUALS = [
    ("01_学生使用手册.md",      "01_学生使用手册.pdf",      "生化学院党员信息管理系统", "学生使用手册"),
    ("02_教师使用手册.md",      "02_教师使用手册.pdf",      "生化学院党员信息管理系统", "教师使用手册"),
    ("03_管理员使用手册.md",    "03_管理员使用手册.pdf",    "生化学院党员信息管理系统", "管理员使用手册"),
    ("04_超级管理员使用手册.md","04_超级管理员使用手册.pdf","生化学院党员信息管理系统", "超级管理员使用手册"),
]


# ── 字体注册 ────────────────────────────────────────────────────────────────
def register_fonts() -> None:
    pdfmetrics.registerFont(UnicodeCIDFont(FONT_NAME))


# ── 样式定义 ────────────────────────────────────────────────────────────────
def make_styles():
    styles = getSampleStyleSheet()

    base = ParagraphStyle(
        "BaseCN",
        parent=styles["BodyText"],
        fontName=FONT_NAME,
        fontSize=10.5,
        leading=18,
        textColor=colors.HexColor("#1F2937"),
        spaceAfter=4,
    )
    styles.add(base)

    styles.add(ParagraphStyle(
        "SysTitleCN", parent=base,
        fontSize=20, leading=28, alignment=TA_CENTER,
        textColor=colors.HexColor("#7F1D1D"),
        spaceBefore=60, spaceAfter=6,
    ))
    styles.add(ParagraphStyle(
        "ManualTitleCN", parent=base,
        fontSize=28, leading=36, alignment=TA_CENTER,
        textColor=colors.HexColor("#991B1B"),
        spaceAfter=14,
    ))
    styles.add(ParagraphStyle(
        "MetaCN", parent=base,
        fontSize=9.5, leading=15, alignment=TA_CENTER,
        textColor=colors.HexColor("#6B7280"),
        spaceAfter=6,
    ))
    styles.add(ParagraphStyle(
        "TOCTitleCN", parent=base,
        fontSize=16, leading=22, alignment=TA_LEFT,
        textColor=colors.HexColor("#991B1B"),
        spaceBefore=4, spaceAfter=10,
    ))
    styles.add(ParagraphStyle(
        "Heading1CN", parent=base,
        fontSize=17, leading=24,
        textColor=colors.HexColor("#111827"),
        spaceBefore=18, spaceAfter=8,
        borderPadding=(0, 0, 4, 0),
    ))
    styles.add(ParagraphStyle(
        "Heading2CN", parent=base,
        fontSize=13.5, leading=20,
        textColor=colors.HexColor("#1E3A5F"),
        spaceBefore=14, spaceAfter=6,
        leftIndent=4,
    ))
    styles.add(ParagraphStyle(
        "Heading3CN", parent=base,
        fontSize=11.5, leading=18,
        textColor=colors.HexColor("#374151"),
        spaceBefore=10, spaceAfter=4,
        leftIndent=8,
    ))
    styles.add(ParagraphStyle(
        "Heading4CN", parent=base,
        fontSize=10.5, leading=17,
        textColor=colors.HexColor("#4B5563"),
        spaceBefore=8, spaceAfter=3,
        leftIndent=12,
    ))
    styles.add(ParagraphStyle(
        "CodeCN", parent=base,
        fontName="Courier", fontSize=8.5, leading=13,
        backColor=colors.HexColor("#F3F4F6"),
        borderColor=colors.HexColor("#D1D5DB"),
        borderWidth=0.5, borderPadding=6, borderRadius=3,
        leftIndent=8, rightIndent=8,
        spaceBefore=4, spaceAfter=8,
    ))
    styles.add(ParagraphStyle(
        "NoteBoxCN", parent=base,
        fontSize=10, leading=16,
        backColor=colors.HexColor("#FFFBEB"),
        borderColor=colors.HexColor("#F59E0B"),
        borderWidth=0.8, borderPadding=6,
        leftIndent=6, rightIndent=6,
        spaceBefore=4, spaceAfter=8,
        textColor=colors.HexColor("#92400E"),
    ))

    toc_level_1 = ParagraphStyle(
        "TOCLevel1CN", parent=base,
        fontSize=10.5, leading=17,
        leftIndent=8, firstLineIndent=0,
        spaceBefore=3,
        textColor=colors.HexColor("#111827"),
    )
    toc_level_2 = ParagraphStyle(
        "TOCLevel2CN", parent=base,
        fontSize=9.8, leading=15,
        leftIndent=24, firstLineIndent=0,
        textColor=colors.HexColor("#374151"),
    )

    return styles, toc_level_1, toc_level_2


# ── 页模板（页眉、页脚、水印） ───────────────────────────────────────────────
class DocTemplate(BaseDocTemplate):
    def __init__(self, filename: str, manual_title: str, **kwargs):
        super().__init__(filename, **kwargs)
        self._manual_title = manual_title
        self._heading_seq = 0
        frame = Frame(
            self.leftMargin, self.bottomMargin,
            self.width, self.height, id="normal",
        )
        template = PageTemplate(id="main", frames=[frame], onPage=self._draw_page)
        self.addPageTemplates([template])

    def beforeDocument(self):
        self._heading_seq = 0

    def afterFlowable(self, flowable):
        style_name = getattr(getattr(flowable, "style", None), "name", "")
        if style_name not in {"Heading1CN", "Heading2CN"}:
            return
        self._heading_seq += 1
        key = f"h{self._heading_seq:04d}"
        text = flowable.getPlainText()
        level = 0 if style_name == "Heading1CN" else 1
        self.canv.bookmarkPage(key)
        self.canv.addOutlineEntry(text, key, level=level, closed=False)
        self.notify("TOCEntry", (level, text, self.page, key))

    def _draw_page(self, canvas, doc):
        canvas.saveState()

        # 页眉：手册名称
        canvas.setFont(FONT_NAME, 8.5)
        canvas.setFillColor(colors.HexColor("#6B7280"))
        canvas.drawCentredString(
            PAGE_WIDTH / 2,
            PAGE_HEIGHT - 10 * mm,
            self._manual_title,
        )
        canvas.setStrokeColor(colors.HexColor("#E5E7EB"))
        canvas.setLineWidth(0.5)
        canvas.line(
            doc.leftMargin, PAGE_HEIGHT - 12 * mm,
            PAGE_WIDTH - doc.rightMargin, PAGE_HEIGHT - 12 * mm,
        )

        # 页脚：版权 + 页码
        canvas.setFont(FONT_NAME, 8.5)
        canvas.setFillColor(colors.HexColor("#6B7280"))
        canvas.drawString(doc.leftMargin, 9 * mm, COPYRIGHT)
        canvas.drawRightString(
            PAGE_WIDTH - doc.rightMargin, 9 * mm,
            f"第 {canvas.getPageNumber()} 页",
        )
        canvas.setStrokeColor(colors.HexColor("#E5E7EB"))
        canvas.line(
            doc.leftMargin, 12 * mm,
            PAGE_WIDTH - doc.rightMargin, 12 * mm,
        )

        # 水印
        canvas.setFillColor(colors.Color(0.70, 0.70, 0.70, alpha=0.07))
        canvas.setFont(FONT_NAME, 17)
        for row in range(7):
            y = 35 * mm + row * 34 * mm
            for x in [28 * mm, 83 * mm, 138 * mm]:
                canvas.saveState()
                canvas.translate(x, y)
                canvas.rotate(28)
                canvas.drawString(0, 0, WATERMARK)
                canvas.restoreState()

        canvas.restoreState()


# ── 内联标记转换（加粗、斜体、行内代码、链接） ────────────────────────────────
def inline_markup(text: str) -> str:
    escaped = html.escape(text)
    escaped = re.sub(r"`([^`]+)`", r'<font face="Courier">\1</font>', escaped)
    escaped = re.sub(r"\*\*([^*]+)\*\*", r"<b>\1</b>", escaped)
    escaped = re.sub(r"\*([^*]+)\*", r"<i>\1</i>", escaped)
    # 外部链接（http/https）
    escaped = re.sub(
        r"\[([^\]]+)\]\((https?://[^)]+)\)",
        r'<a href="\2" color="#2563EB">\1</a>',
        escaped,
    )
    # 内部锚链接（以#开头）— PDF 内跳转不支持，直接显示文本
    escaped = re.sub(r"\[([^\]]+)\]\(#[^)]*\)", r"\1", escaped)
    return escaped


# ── 工具函数 ────────────────────────────────────────────────────────────────
def is_table_separator(line: str) -> bool:
    s = line.strip()
    return bool(s) and all(ch in "|:- " for ch in s)


def split_table_row(line: str) -> list[str]:
    return [c.strip() for c in line.strip().strip("|").split("|")]


def clean_lines(md_text: str) -> list[str]:
    """去掉 Markdown 文件开头的 ## 目录 ... --- 块，由脚本自动生成 TOC。"""
    lines = md_text.replace("\r\n", "\n").replace("\r", "\n").split("\n")
    result: list[str] = []
    skip_toc = False

    for line in lines:
        m = re.match(r"^(#{1,3})\s+(.+?)\s*$", line)
        if m and m.group(2).strip() in {"目录", "目 录"}:
            skip_toc = True
            continue
        if skip_toc:
            if line.strip() == "---":
                skip_toc = False
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

        # 代码块
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

        # 分割线
        if stripped == "---":
            blocks.append(("hr", None))
            i += 1
            continue

        # 标题
        m = re.match(r"^(#{1,4})\s+(.+?)\s*$", line)
        if m:
            blocks.append(("heading", (len(m.group(1)), m.group(2).strip())))
            i += 1
            continue

        # 表格
        if stripped.startswith("|") and i + 1 < len(lines) and is_table_separator(lines[i + 1]):
            table_lines = [line, lines[i + 1]]
            i += 2
            while i < len(lines) and lines[i].strip().startswith("|"):
                table_lines.append(lines[i])
                i += 1
            blocks.append(("table", table_lines))
            continue

        # 无序列表
        if re.match(r"^[-*]\s+", stripped):
            items = []
            while i < len(lines) and re.match(r"^[-*]\s+", lines[i].strip()):
                items.append(re.sub(r"^[-*]\s+", "", lines[i].strip()))
                i += 1
            blocks.append(("ul", items))
            continue

        # 有序列表
        if re.match(r"^\d+\.\s+", stripped):
            items = []
            while i < len(lines) and re.match(r"^\d+\.\s+", lines[i].strip()):
                items.append(re.sub(r"^\d+\.\s+", "", lines[i].strip()))
                i += 1
            blocks.append(("ol", items))
            continue

        # 注意/警告块（以 > ⚠️ 开头）
        if stripped.startswith(">"):
            note_lines = []
            while i < len(lines) and lines[i].strip().startswith(">"):
                note_lines.append(lines[i].strip().lstrip(">").strip())
                i += 1
            blocks.append(("note", " ".join(note_lines)))
            continue

        # 普通段落
        para_lines = [stripped]
        i += 1
        while i < len(lines):
            nxt = lines[i].strip()
            if not nxt:
                break
            if (
                nxt.startswith("#") or nxt == "---"
                or nxt.startswith("|") or nxt.startswith("```")
                or nxt.startswith("~~~") or nxt.startswith(">")
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
    body = rows[2:]  # row[1] 是分隔行

    data = [[Paragraph(inline_markup(cell), base_style) for cell in header]]
    for row in body:
        norm = row + [""] * (len(header) - len(row))
        data.append([Paragraph(inline_markup(cell), base_style) for cell in norm[:len(header)]])

    col_count = len(header)
    available = PAGE_WIDTH - 36 * mm  # 左右各18mm
    col_w = available / col_count

    tbl = Table(data, colWidths=[col_w] * col_count, repeatRows=1)
    tbl.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#FEF2F2")),
        ("TEXTCOLOR",  (0, 0), (-1, 0), colors.HexColor("#111827")),
        ("FONTNAME",   (0, 0), (-1, 0), FONT_NAME),
        ("FONTSIZE",   (0, 0), (-1, 0), 10),
        ("GRID",       (0, 0), (-1, -1), 0.5, colors.HexColor("#E5E7EB")),
        ("VALIGN",     (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING",  (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING",   (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING",(0, 0), (-1, -1), 5),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1),
         [colors.white, colors.HexColor("#F9FAFB")]),
    ]))
    return tbl


# ── 主要构建逻辑 ─────────────────────────────────────────────────────────────
def build_story(md_path: Path, sys_title: str, manual_title: str):
    styles, toc_level_1, toc_level_2 = make_styles()

    toc = TableOfContents()
    toc.levelStyles = [toc_level_1, toc_level_2]
    toc.dotsMinLevel = 0

    story = [
        # 封面
        Spacer(1, 20 * mm),
        Paragraph(sys_title, styles["SysTitleCN"]),
        Paragraph(manual_title, styles["ManualTitleCN"]),
        HRFlowable(width="60%", thickness=1.5,
                   color=colors.HexColor("#991B1B"),
                   hAlign="CENTER", spaceAfter=20),
        Paragraph(f"版本：v2.0.4　　编制日期：{TODAY}", styles["MetaCN"]),
        PageBreak(),
        # 目录页
        Paragraph("目　录", styles["TOCTitleCN"]),
        HRFlowable(width="100%", thickness=0.6,
                   color=colors.HexColor("#E5E7EB"), spaceAfter=8),
        toc,
        PageBreak(),
    ]

    lines = clean_lines(md_path.read_text(encoding="utf-8"))
    blocks = parse_blocks(lines)

    for block_type, payload in blocks:
        if block_type == "heading":
            level, text = payload
            style_map = {
                1: "Heading1CN",
                2: "Heading2CN",
                3: "Heading3CN",
                4: "Heading4CN",
            }
            s = styles[style_map.get(level, "Heading4CN")]
            story.append(Paragraph(inline_markup(text), s))
            # 一级标题后加分割线
            if level == 1:
                story.append(HRFlowable(
                    width="100%", thickness=0.7,
                    color=colors.HexColor("#E5E7EB"), spaceAfter=4,
                ))

        elif block_type == "p":
            story.append(Paragraph(inline_markup(payload), styles["BaseCN"]))
            story.append(Spacer(1, 2))

        elif block_type == "code":
            story.append(Preformatted(payload, styles["CodeCN"]))

        elif block_type == "hr":
            story.append(HRFlowable(
                width="100%", thickness=0.7,
                color=colors.HexColor("#D1D5DB"),
            ))
            story.append(Spacer(1, 6))

        elif block_type == "note":
            story.append(Paragraph(inline_markup(payload), styles["NoteBoxCN"]))

        elif block_type in {"ul", "ol"}:
            items = [
                ListItem(
                    Paragraph(inline_markup(item), styles["BaseCN"]),
                    leftIndent=12,
                )
                for item in payload
            ]
            if block_type == "ul":
                # bulletType="bullet" 时不传 start，否则 start="1" 会把符号渲染成字符"1"
                story.append(ListFlowable(
                    items,
                    bulletType="bullet",
                    bulletColor=colors.HexColor("#991B1B"),
                    leftIndent=16,
                ))
            else:
                # 有序列表：bulletType="1" + start="1" 才是正确的顺序编号
                story.append(ListFlowable(
                    items,
                    bulletType="1",
                    start="1",
                    leftIndent=16,
                ))
            story.append(Spacer(1, 4))

        elif block_type == "table":
            story.append(build_table(payload, styles["BaseCN"]))
            story.append(Spacer(1, 6))

    return story


# ── PDF 生成入口 ─────────────────────────────────────────────────────────────
def generate_pdf(
    md_name: str, pdf_name: str,
    sys_title: str, manual_title: str,
) -> None:
    md_path  = DOCS_DIR / md_name
    pdf_path = DOCS_DIR / pdf_name

    if not md_path.exists():
        print(f"  [跳过] 找不到文件：{md_path}")
        return

    full_title = f"{sys_title} — {manual_title}"
    doc = DocTemplate(
        str(pdf_path),
        manual_title=full_title,
        pagesize=A4,
        leftMargin=18 * mm,
        rightMargin=18 * mm,
        topMargin=22 * mm,
        bottomMargin=20 * mm,
        title=full_title,
        author="生化学院党员信息管理系统",
        subject=manual_title,
        creator="generate_manuals_pdf.py",
    )

    story = build_story(md_path, sys_title, manual_title)
    # multiBuild 运行两遍：第一遍计算页码，第二遍写入 TOC 页码
    doc.multiBuild(story)
    print(f"  [OK] 已生成：{pdf_path.name}")


def main() -> None:
    register_fonts()
    print(f"开始生成用户使用手册 PDF（共 {len(MANUALS)} 份）…\n")
    for md_name, pdf_name, sys_title, manual_title in MANUALS:
        print(f"处理：{md_name}")
        generate_pdf(md_name, pdf_name, sys_title, manual_title)
    print("\n全部完成！PDF 文件保存在 docs/ 目录下。")


if __name__ == "__main__":
    main()
