// Dò chữ giao diện viết thẳng trong code của app học (thay vì lấy từ từ điển ngôn ngữ qua t()).
// Đọc code bằng bộ phân tích của TypeScript nên không nhầm với chú thích, tên lớp CSS hay đường dẫn.
import ts from 'typescript';

export type ChuVietThang = { dong: number; chu: string };

/** Thuộc tính JSX hiện chữ cho người dùng (cả thuộc tính của component tự viết như label, legend). */
const THUOC_TINH_CHU = new Set(['aria-label', 'aria-description', 'aria-valuetext', 'title', 'placeholder', 'alt', 'label', 'legend', 'what', 'message', 'hint']);

/** Chữ cái chỉ tiếng Việt mới có (dấu thanh, ă â đ ê ô ơ ư): gặp trong chuỗi bất kỳ là chữ giao diện viết thẳng. */
const CHU_VIET = /[ăâđêôơưàáảãạằắẳẵặầấẩẫậèéẻẽẹềếểễệìíỉĩịòóỏõọồốổỗộờớởỡợùúủũụừứửữựỳýỷỹỵ]/i;
const CO_CHU_CAI = /\p{L}/u;

function trongThrowHoacConsole(node: ts.Node): boolean {
    for (let p: ts.Node | undefined = node.parent; p; p = p.parent) {
        if (ts.isThrowStatement(p)) return true;
        if (ts.isCallExpression(p) && p.expression.getText().startsWith('console.')) return true;
    }
    return false;
}

function laGiaTriThuocTinhChu(node: ts.Node): boolean {
    let p = node.parent;
    if (p && ts.isJsxExpression(p)) p = p.parent;
    return !!p && ts.isJsxAttribute(p) && THUOC_TINH_CHU.has(p.name.getText());
}

export function timChuVietThang(tenFile: string, ma: string): ChuVietThang[] {
    const sf = ts.createSourceFile(tenFile, ma, ts.ScriptTarget.Latest, true, tenFile.endsWith('.tsx') ? ts.ScriptKind.TSX : ts.ScriptKind.TS);
    const kq: ChuVietThang[] = [];
    const bao = (node: ts.Node, chu: string) => kq.push({ dong: sf.getLineAndCharacterOfPosition(node.getStart(sf)).line + 1, chu: chu.trim() });

    const di = (node: ts.Node) => {
        if (ts.isJsxText(node)) {
            if (CO_CHU_CAI.test(node.text)) bao(node, node.text);
        } else if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node) || ts.isTemplateHead(node) || ts.isTemplateMiddle(node) || ts.isTemplateTail(node)) {
            const chu = node.text;
            const laThuocTinh = laGiaTriThuocTinhChu(node) || (ts.isTemplateHead(node) && laGiaTriThuocTinhChu(node.parent));
            if ((CHU_VIET.test(chu) || (laThuocTinh && CO_CHU_CAI.test(chu))) && !trongThrowHoacConsole(node)) bao(node, chu);
        }
        ts.forEachChild(node, di);
    };
    di(sf);
    return kq;
}
