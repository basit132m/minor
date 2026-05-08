# XMR Miner

A simple Windows GUI application for mining Monero (XMR) using your CPU.  
Powered by [XMRig](https://github.com/xmrig/xmrig) — the leading open-source Monero miner.

---

## Features

- Clean dark-theme GUI
- Auto-downloads XMRig on first launch
- Configurable wallet, pool, threads, and CPU usage cap
- Live hashrate display
- Settings saved between sessions (`config.json`)

---

## Quick Start

### Option A — Run from source (Python)

**Requirements:** Python 3.9+

```bash
pip install -r requirements.txt
python miner.py
```

### Option B — Build a standalone .exe (Windows)

**Requirements:** Python 3.9+, pip

```batch
build.bat
```

The output executable will be at `dist\XMR_Miner.exe`.  
Copy it anywhere — on first launch it downloads XMRig automatically into the same folder.

---

## Configuration

| Field | Description | Default |
|-------|-------------|---------|
| Wallet Address | Your XMR wallet address | *(required)* |
| Pool:Port | Mining pool address | `pool.supportxmr.com:3333` |
| Worker Name | Label for this rig | `worker1` |
| Threads | CPU threads (0 = auto) | `0` |
| Max CPU % | CPU usage hint | `75` |

Settings are saved to `config.json` automatically on each start.

---

## Popular Mining Pools

| Pool | Address |
|------|---------|
| SupportXMR | `pool.supportxmr.com:3333` |
| MoneroOcean | `gulf.moneroocean.stream:10128` |
| Nanopool | `xmr-eu1.nanopool.org:14444` |

---

## Notes

- XMRig is downloaded from its official GitHub releases page.
- Mining profitability depends on your CPU's hashrate and XMR's price.
- Reduce `Max CPU %` if you want to mine in the background without impacting other tasks.
