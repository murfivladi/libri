import importlib.util
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location("deapi", Path(__file__).parents[1] / "deapi.py")
deapi = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deapi)


class DeapiTests(unittest.TestCase):
    def test_config(self):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "config.yaml"
            path.write_text('deapi:\n  api_key: "123|secret"\n')
            self.assertEqual(deapi.load_key(path), "123|secret")
            path.write_text('deapi:\n  api_key: "INSERISCI_LA_CHIAVE"\n')
            with self.assertRaises(ValueError):
                deapi.load_key(path)

    def test_poll(self):
        with patch.object(deapi, "api_call", side_effect=[{"status": "pending"}, {"status": "done", "result_url": "https://example.org/i.png"}]), patch.object(deapi.time, "sleep"):
            self.assertEqual(deapi.wait_for_job("key", "id", 10, 1)["status"], "done")

    def test_failed_job(self):
        with patch.object(deapi, "api_call", return_value={"status": "error"}):
            with self.assertRaises(RuntimeError):
                deapi.wait_for_job("key", "id", 10, 1)

    def test_resume_does_not_submit(self):
        with tempfile.TemporaryDirectory() as folder, patch.object(deapi, "load_key", return_value="secret"), patch.object(deapi, "api_call") as api, patch.object(deapi, "wait_for_job", return_value={"result_url": "https://example.org/i.png"}), patch.object(deapi, "download") as download:
            self.assertEqual(deapi.main(["--request-id", "existing", "-o", folder + "/a.png"]), 0)
            api.assert_not_called()
            download.assert_called_once()

    def test_existing_output_prevents_spending(self):
        with tempfile.TemporaryDirectory() as folder, patch.object(deapi, "api_call") as api:
            output = Path(folder) / "a.png"
            output.write_bytes(b"existing")
            self.assertEqual(deapi.main(["-p", "test", "-o", str(output)]), 1)
            api.assert_not_called()
            self.assertEqual(output.read_bytes(), b"existing")

    def test_download_atomic_and_no_auth(self):
        with tempfile.TemporaryDirectory() as folder, patch.object(deapi.requests, "get") as get:
            response = get.return_value.__enter__.return_value
            response.ok = True
            response.headers = {"Content-Type": "image/png"}
            response.iter_content.return_value = [b"image"]
            output = Path(folder) / "a.png"
            deapi.download("https://example.org/i.png", output)
            self.assertEqual(output.read_bytes(), b"image")
            self.assertNotIn("headers", get.call_args.kwargs)
            self.assertEqual(list(Path(folder).iterdir()), [output])


if __name__ == "__main__":
    unittest.main()
