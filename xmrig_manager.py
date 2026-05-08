"""
Downloads and manages the XMRig binary for Windows.
XMRig is the industry-standard open-source Monero CPU miner.
Project: https://github.com/xmrig/xmrig
"""

import os
import sys
import zipfile
import urllib.request
import urllib.error

# Latest stable XMRig Windows release
XMRIG_VERSION = "6.22.2"
XMRIG_URL = (
    f"https://github.com/xmrig/xmrig/releases/download/v{XMRIG_VERSION}/"
    f"xmrig-{XMRIG_VERSION}-msvc-win64.zip"
)
XMRIG_ZIP  = f"xmrig-{XMRIG_VERSION}-msvc-win64.zip"
XMRIG_DIR  = f"xmrig-{XMRIG_VERSION}"
XMRIG_EXE  = os.path.join(XMRIG_DIR, "xmrig.exe")


class XMRigManager:
    """Ensures the XMRig binary is available locally, downloading if needed."""

    def ensure_xmrig(self, log=print) -> str:
        """Return the path to xmrig.exe, downloading it first if necessary."""
        if os.path.isfile(XMRIG_EXE):
            log(f"XMRig found: {XMRIG_EXE}")
            return os.path.abspath(XMRIG_EXE)

        log(f"XMRig not found. Downloading v{XMRIG_VERSION}…")
        self._download(log)
        self._extract(log)

        if not os.path.isfile(XMRIG_EXE):
            raise FileNotFoundError(
                f"xmrig.exe not found after extraction in '{XMRIG_DIR}'"
            )

        log(f"XMRig ready: {XMRIG_EXE}")
        return os.path.abspath(XMRIG_EXE)

    # ---------------------------------------------------------------- helpers --

    def _download(self, log):
        log(f"Downloading: {XMRIG_URL}")

        def _progress(count, block, total):
            if total > 0:
                pct = min(100, int(count * block * 100 / total))
                log(f"  Downloading… {pct}%")

        try:
            urllib.request.urlretrieve(XMRIG_URL, XMRIG_ZIP, reporthook=_progress)
        except urllib.error.URLError as e:
            raise RuntimeError(f"Download failed: {e}") from e

        log("Download complete.")

    def _extract(self, log):
        log(f"Extracting {XMRIG_ZIP}…")
        with zipfile.ZipFile(XMRIG_ZIP, "r") as z:
            z.extractall(".")
        os.remove(XMRIG_ZIP)
        log("Extraction complete.")
