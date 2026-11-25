#!/usr/bin/env python3
"""Kiểm tra file nhập nội dung Lernbuch (định dạng lernbuch-import v1).

Dùng:  python3 validate_import.py <file.lernbuch.json> [--json]
Thoát với mã 1 nếu có lỗi; cảnh báo không làm hỏng lượt nhập.
Trang quản trị chạy đúng các quy tắc này trước khi cho chạy thử.
"""
import json
import pathlib
import sys

from jsonschema import Draft202012Validator

SCHEMA_PATH = pathlib.Path(__file__).with_name("lernbuch-import.schema.json")
ARTICLE_GENDER = {"der": {"m"}, "das": {"n"}, "die": {"f", "pl"}}
BIG_FILE_CARDS = 300


def _issue(kind, code, path, message):
    return {"kind": kind, "code": code, "path": path, "message": message}


def validate(data):
    errors, warnings = [], []

    schema = json.loads(SCHEMA_PATH.read_text(encoding="utf-8"))
    for e in sorted(Draft202012Validator(schema).iter_errors(data), key=lambda e: list(e.path)):
        path = "/".join(str(p) for p in e.path) or "(gốc)"
        errors.append(_issue("error", "schema", path, e.message))
    if errors:  # cấu trúc sai thì dừng, các quy tắc sau giả định đúng cấu trúc
        return errors, warnings

    topics = data.get("topics", [])
    cards = data.get("cards", [])
    grammar = data.get("grammar", [])
    exercises = data.get("exercises", [])
    verbs = data.get("verbs", [])

    # R1: khoá không trùng trong từng danh sách
    for name, items, field in (("topics", topics, "key"), ("cards", cards, "key"), ("grammar", grammar, "key"),
                               ("exercises", exercises, "key"), ("verbs", verbs, "infinitive")):
        seen = {}
        for i, it in enumerate(items):
            k = it[field]
            if k in seen:
                errors.append(_issue("error", "dup_key", f"{name}/{i}/{field}",
                                     f"khoá '{k}' trùng với {name}/{seen[k]}"))
            else:
                seen[k] = i

    # R2: tham chiếu phải có trong file, hoặc bắt đầu bằng @ (mục đã có trên web, kiểm lúc nhập)
    topic_keys = {t["key"] for t in topics}
    grammar_keys = {g["key"] for g in grammar}

    def check_ref(value, pool, path, what):
        if value is None or value.startswith("@"):
            return
        if value not in pool:
            errors.append(_issue("error", "missing_ref", path, f"{what} '{value}' không có trong file"))

    for i, t in enumerate(topics):
        check_ref(t.get("parent"), topic_keys, f"topics/{i}/parent", "chủ đề cha")
        if t.get("parent") == t["key"]:
            errors.append(_issue("error", "missing_ref", f"topics/{i}/parent", "chủ đề không thể là cha của chính nó"))
    for i, c in enumerate(cards):
        check_ref(c["topic"], topic_keys, f"cards/{i}/topic", "chủ đề")
    for i, x in enumerate(exercises):
        check_ref(x.get("grammar"), grammar_keys, f"exercises/{i}/grammar", "bài ngữ pháp")

    # R3: danh từ phải có giống, và khớp với mạo từ ở mặt trước
    for i, c in enumerate(cards):
        first = c["front"].split(" ", 1)[0].lower()
        article = first if first in ARTICLE_GENDER and " " in c["front"] else None
        if c.get("pos") == "noun" and "gender" not in c:
            errors.append(_issue("error", "gender", f"cards/{i}/gender", "danh từ thiếu giống (m, f, n, pl)"))
        if article and "gender" in c and c["gender"] not in ARTICLE_GENDER[article]:
            errors.append(_issue("error", "gender", f"cards/{i}/gender",
                                 f"'{c['front']}' dùng '{article}' nhưng giống ghi '{c['gender']}'"))

    # R4: nguồn chưa có quyền thì không được chép nguyên văn
    if data["source"]["license"] in ("unknown", "forbidden"):
        def no_verbatim(obj, path):
            if obj.get("origin") == "source":
                errors.append(_issue("error", "license", path,
                                     "nguồn chưa có quyền dùng (license unknown/forbidden) nên không được chép nguyên văn; hãy viết mới"))
        for i, c in enumerate(cards):
            no_verbatim(c, f"cards/{i}")
            for j, ex in enumerate(c["examples"]):
                no_verbatim(ex, f"cards/{i}/examples/{j}")
        for i, g in enumerate(grammar):
            no_verbatim(g, f"grammar/{i}")
        for i, x in enumerate(exercises):
            no_verbatim(x, f"exercises/{i}")

    # R5: bài tập phải chấm được
    for i, x in enumerate(exercises):
        if x["type"] == "choice":
            if "choices" not in x:
                errors.append(_issue("error", "choice", f"exercises/{i}", "bài chọn đáp án thiếu choices"))
            else:
                missing = [a for a in x["answers"] if a not in x["choices"]]
                if missing:
                    errors.append(_issue("error", "choice", f"exercises/{i}/answers",
                                         f"đáp án không nằm trong lựa chọn: {missing}"))
        if x["type"] == "gap" and "___" not in x["prompt"]:
            errors.append(_issue("error", "gap", f"exercises/{i}/prompt", "bài điền từ phải có chỗ trống ___"))

    # Cảnh báo: không chặn lượt nhập nhưng hiện cho biên tập viên xem
    fronts = {}
    for i, c in enumerate(cards):
        lemma = (c.get("lemma") or c["front"].split(" ")[-1]).lower()
        stem = lemma[:4]
        for j, ex in enumerate(c["examples"]):
            if stem and stem not in ex["de"].lower():
                warnings.append(_issue("warning", "example_lemma", f"cards/{i}/examples/{j}",
                                       f"câu ví dụ có vẻ không chứa từ '{lemma}'"))
        f = c["front"].strip().lower()
        if f in fronts:
            warnings.append(_issue("warning", "dup_front", f"cards/{i}/front",
                                   f"mặt trước trùng với cards/{fronts[f]}"))
        else:
            fronts[f] = i
    if len(cards) > BIG_FILE_CARDS:
        warnings.append(_issue("warning", "big_file", "cards", f"{len(cards)} thẻ; nên chia file dưới {BIG_FILE_CARDS} thẻ"))

    return errors, warnings


def main(argv):
    if len(argv) < 2:
        print(__doc__)
        return 2
    path = pathlib.Path(argv[1])
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as e:
        print(f"Không đọc được file JSON: {e}")
        return 1
    errors, warnings = validate(data)
    counts = {k: len(data.get(k, [])) for k in ("topics", "cards", "grammar", "exercises", "verbs")}
    if "--json" in argv:
        print(json.dumps({"counts": counts, "errors": errors, "warnings": warnings}, ensure_ascii=False, indent=1))
    else:
        print(f"{path.name}: " + ", ".join(f"{v} {k}" for k, v in counts.items()))
        for it in errors + warnings:
            tag = "LỖI" if it["kind"] == "error" else "Lưu ý"
            print(f"  {tag} [{it['code']}] {it['path']}: {it['message']}")
        print("Kết quả: " + ("có lỗi, chưa nhập được" if errors else "đạt, có thể chạy thử"))
    return 1 if errors else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
