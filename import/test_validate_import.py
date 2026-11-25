"""Test cho validate_import.py: file mẫu phải đạt, mỗi lỗi hay gặp phải bị bắt.

Chạy:  python3 -m unittest test_validate_import.py -v
"""
import copy
import json
import pathlib
import subprocess
import sys
import unittest

HERE = pathlib.Path(__file__).parent
sys.path.insert(0, str(HERE))
from validate_import import validate  # noqa: E402

SAMPLE = json.loads((HERE / "vi-du-a1-essen.lernbuch.json").read_text(encoding="utf-8"))


def codes(issues):
    return {i["code"] for i in issues}


class ValidateImportTest(unittest.TestCase):
    def mutate(self, fn):
        data = copy.deepcopy(SAMPLE)
        fn(data)
        return validate(data)

    def test_sample_passes_without_errors_or_warnings(self):
        errors, warnings = validate(copy.deepcopy(SAMPLE))
        self.assertEqual(errors, [])
        self.assertEqual(warnings, [])

    def test_missing_source_is_schema_error(self):
        errors, _ = self.mutate(lambda d: d.pop("source"))
        self.assertIn("schema", codes(errors))

    def test_bad_level_is_schema_error(self):
        errors, _ = self.mutate(lambda d: d["cards"][0].update(level="A3"))
        self.assertIn("schema", codes(errors))

    def test_unknown_field_is_schema_error(self):
        errors, _ = self.mutate(lambda d: d["cards"][0].update(meaning="quả táo"))
        self.assertIn("schema", codes(errors))

    def test_duplicate_card_key(self):
        errors, _ = self.mutate(lambda d: d["cards"].append(copy.deepcopy(d["cards"][0])))
        self.assertIn("dup_key", codes(errors))

    def test_card_points_to_missing_topic(self):
        errors, _ = self.mutate(lambda d: d["cards"][0].update(topic="khong-co"))
        self.assertIn("missing_ref", codes(errors))

    def test_reference_to_existing_web_topic_is_allowed(self):
        errors, _ = self.mutate(lambda d: d["cards"][0].update(topic="@f0001"))
        self.assertEqual(errors, [])

    def test_topic_cannot_be_its_own_parent(self):
        errors, _ = self.mutate(lambda d: d["topics"][0].update(parent="a1-essen"))
        self.assertIn("missing_ref", codes(errors))

    def test_article_and_gender_must_match(self):
        errors, _ = self.mutate(lambda d: d["cards"][0].update(gender="f"))  # der Apfel ghi giống cái
        self.assertIn("gender", codes(errors))

    def test_noun_without_gender(self):
        errors, _ = self.mutate(lambda d: d["cards"][1].pop("gender"))
        self.assertIn("gender", codes(errors))

    def test_verbatim_copy_from_unlicensed_source_is_blocked(self):
        def f(d):
            d["source"].update(kind="book", license="unknown")
            d["cards"][0]["examples"][0]["origin"] = "source"
        errors, _ = self.mutate(f)
        self.assertIn("license", codes(errors))

    def test_verbatim_copy_from_licensed_source_is_allowed(self):
        def f(d):
            d["source"].update(kind="book", license="licensed")
            d["cards"][0]["examples"][0]["origin"] = "source"
        errors, _ = self.mutate(f)
        self.assertEqual(errors, [])

    def test_choice_answer_must_be_a_choice(self):
        errors, _ = self.mutate(lambda d: d["exercises"][0].update(answers=["den"]))
        self.assertIn("choice", codes(errors))

    def test_choice_without_choices(self):
        errors, _ = self.mutate(lambda d: d["exercises"][0].pop("choices"))
        self.assertIn("choice", codes(errors))

    def test_gap_needs_blank(self):
        errors, _ = self.mutate(lambda d: d["exercises"][1].update(prompt="Ich möchte zwei Brötchen."))
        self.assertIn("gap", codes(errors))

    def test_verb_missing_person_is_schema_error(self):
        errors, _ = self.mutate(lambda d: d["verbs"][0]["conjugation"]["praesens"].pop("ihr"))
        self.assertIn("schema", codes(errors))

    def test_example_without_the_word_is_only_a_warning(self):
        errors, warnings = self.mutate(lambda d: d["cards"][0]["examples"][0].update(de="Ich trinke Wasser."))
        self.assertEqual(errors, [])
        self.assertIn("example_lemma", codes(warnings))

    def test_duplicate_front_is_only_a_warning(self):
        def f(d):
            c = copy.deepcopy(d["cards"][0])
            c["key"] = "de-apfel-2"
            d["cards"].append(c)
        errors, warnings = self.mutate(f)
        self.assertEqual(errors, [])
        self.assertIn("dup_front", codes(warnings))

    def test_cli_exit_codes(self):
        ok = subprocess.run([sys.executable, str(HERE / "validate_import.py"), str(HERE / "vi-du-a1-essen.lernbuch.json")],
                            capture_output=True, text=True)
        self.assertEqual(ok.returncode, 0, ok.stdout + ok.stderr)
        bad_path = HERE / "_tmp_bad.json"
        bad_path.write_text("{ không phải json", encoding="utf-8")
        try:
            bad = subprocess.run([sys.executable, str(HERE / "validate_import.py"), str(bad_path)],
                                 capture_output=True, text=True)
            self.assertEqual(bad.returncode, 1)
        finally:
            bad_path.unlink()


if __name__ == "__main__":
    unittest.main()
