import tkinter as tk
from tkinter import ttk, scrolledtext, messagebox
import threading
import subprocess
import os
import json
import re
import time
from xmrig_manager import XMRigManager

CONFIG_FILE = "config.json"

DEFAULT_CONFIG = {
    "wallet": "",
    "pool": "pool.supportxmr.com:3333",
    "worker": "worker1",
    "threads": 0,
    "max_cpu_usage": 75
}


def load_config():
    if os.path.exists(CONFIG_FILE):
        with open(CONFIG_FILE, "r") as f:
            return {**DEFAULT_CONFIG, **json.load(f)}
    return DEFAULT_CONFIG.copy()


def save_config(cfg):
    with open(CONFIG_FILE, "w") as f:
        json.dump(cfg, f, indent=2)


class XMRMinerApp(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("XMR Miner")
        self.resizable(False, False)
        self.configure(bg="#1a1a2e")

        self.cfg = load_config()
        self.manager = XMRigManager()
        self.process = None
        self.mining = False
        self.log_lock = threading.Lock()

        self._build_ui()
        self.protocol("WM_DELETE_WINDOW", self._on_close)

    # ------------------------------------------------------------------ UI --

    def _build_ui(self):
        DARK   = "#1a1a2e"
        PANEL  = "#16213e"
        ACCENT = "#e94560"
        TEXT   = "#eaeaea"
        ENTRY  = "#0f3460"

        style = ttk.Style(self)
        style.theme_use("clam")
        style.configure("TLabel",  background=DARK,  foreground=TEXT,  font=("Consolas", 10))
        style.configure("TEntry",  fieldbackground=ENTRY, foreground=TEXT, font=("Consolas", 10))
        style.configure("TFrame",  background=DARK)
        style.configure("TLabelframe",       background=PANEL, foreground=TEXT, font=("Consolas", 10, "bold"))
        style.configure("TLabelframe.Label", background=PANEL, foreground=ACCENT)

        # Header
        hdr = tk.Label(self, text="⛏  XMR MINER", bg=DARK, fg=ACCENT,
                       font=("Consolas", 18, "bold"))
        hdr.grid(row=0, column=0, columnspan=2, pady=(12, 4))

        sub = tk.Label(self, text="Monero CPU Miner  •  powered by XMRig",
                       bg=DARK, fg="#888", font=("Consolas", 9))
        sub.grid(row=1, column=0, columnspan=2, pady=(0, 10))

        # Config frame
        cfg_frame = ttk.LabelFrame(self, text=" Configuration ", padding=10)
        cfg_frame.grid(row=2, column=0, columnspan=2, padx=16, pady=4, sticky="ew")

        labels = ["Wallet Address:", "Pool:Port:", "Worker Name:",
                  "Threads (0=auto):", "Max CPU %:"]
        keys   = ["wallet", "pool", "worker", "threads", "max_cpu_usage"]
        self.vars = {}

        for i, (lbl, key) in enumerate(zip(labels, keys)):
            tk.Label(cfg_frame, text=lbl, bg=PANEL, fg=TEXT,
                     font=("Consolas", 10), anchor="e", width=16).grid(
                row=i, column=0, sticky="e", pady=3, padx=(0, 6))
            var = tk.StringVar(value=str(self.cfg.get(key, "")))
            self.vars[key] = var
            e = tk.Entry(cfg_frame, textvariable=var, bg=ENTRY, fg=TEXT,
                         insertbackground=TEXT, font=("Consolas", 10),
                         relief="flat", width=40)
            e.grid(row=i, column=1, sticky="ew", pady=3)

        # Status bar
        status_frame = ttk.Frame(self, padding=(16, 4))
        status_frame.grid(row=3, column=0, columnspan=2, sticky="ew")

        self.hashrate_var = tk.StringVar(value="Hashrate: ---")
        self.status_var   = tk.StringVar(value="Status: Idle")

        tk.Label(status_frame, textvariable=self.hashrate_var,
                 bg=DARK, fg="#00ff88", font=("Consolas", 11, "bold")).pack(side="left")
        tk.Label(status_frame, textvariable=self.status_var,
                 bg=DARK, fg="#aaa",    font=("Consolas", 10)).pack(side="right")

        # Log box
        log_frame = ttk.LabelFrame(self, text=" Output ", padding=6)
        log_frame.grid(row=4, column=0, columnspan=2, padx=16, pady=4, sticky="nsew")

        self.log_box = scrolledtext.ScrolledText(
            log_frame, state="disabled", height=14, width=72,
            bg="#0a0a1a", fg="#b0ffb0", font=("Consolas", 9),
            relief="flat", insertbackground="#b0ffb0")
        self.log_box.pack(fill="both", expand=True)

        # Buttons
        btn_frame = tk.Frame(self, bg=DARK)
        btn_frame.grid(row=5, column=0, columnspan=2, pady=10)

        self.start_btn = tk.Button(
            btn_frame, text="▶  START MINING", command=self._start,
            bg=ACCENT, fg="white", activebackground="#c73652",
            font=("Consolas", 11, "bold"), relief="flat",
            padx=20, pady=6, cursor="hand2")
        self.start_btn.pack(side="left", padx=8)

        self.stop_btn = tk.Button(
            btn_frame, text="■  STOP", command=self._stop,
            bg="#333", fg="#aaa", activebackground="#555",
            font=("Consolas", 11, "bold"), relief="flat",
            padx=20, pady=6, cursor="hand2", state="disabled")
        self.stop_btn.pack(side="left", padx=8)

        self.grid_rowconfigure(4, weight=1)

    # --------------------------------------------------------------- actions --

    def _start(self):
        wallet = self.vars["wallet"].get().strip()
        if not wallet:
            messagebox.showerror("Missing Wallet", "Please enter your XMR wallet address.")
            return

        self.cfg["wallet"]        = wallet
        self.cfg["pool"]          = self.vars["pool"].get().strip()
        self.cfg["worker"]        = self.vars["worker"].get().strip()
        self.cfg["threads"]       = int(self.vars["threads"].get() or 0)
        self.cfg["max_cpu_usage"] = int(self.vars["max_cpu_usage"].get() or 75)
        save_config(self.cfg)

        self._log("Checking XMRig binary…")
        threading.Thread(target=self._prepare_and_mine, daemon=True).start()

    def _prepare_and_mine(self):
        try:
            xmrig_path = self.manager.ensure_xmrig(self._log)
        except Exception as ex:
            self._log(f"[ERROR] Could not obtain XMRig: {ex}")
            return

        self._mine(xmrig_path)

    def _mine(self, xmrig_path):
        self.mining = True
        self.after(0, self._set_mining_ui, True)

        pool   = self.cfg["pool"]
        wallet = self.cfg["wallet"]
        worker = self.cfg["worker"]
        threads = self.cfg["threads"]
        cpu_pct = self.cfg["max_cpu_usage"]

        cmd = [
            xmrig_path,
            "-o", pool,
            "-u", f"{wallet}.{worker}",
            "-p", "x",
            "--cpu-max-threads-hint", str(cpu_pct),
            "--no-color",
        ]
        if threads > 0:
            cmd += ["-t", str(threads)]

        self._log("Starting XMRig…")
        self._log("Command: " + " ".join(cmd))

        try:
            self.process = subprocess.Popen(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                text=True,
                bufsize=1,
            )
            for line in self.process.stdout:
                line = line.rstrip()
                self._log(line)
                self._parse_hashrate(line)
                if not self.mining:
                    break
        except Exception as ex:
            self._log(f"[ERROR] {ex}")
        finally:
            self.mining = False
            self.after(0, self._set_mining_ui, False)
            self.after(0, lambda: self.hashrate_var.set("Hashrate: ---"))
            self.after(0, lambda: self.status_var.set("Status: Idle"))

    def _stop(self):
        self.mining = False
        if self.process and self.process.poll() is None:
            self.process.terminate()
            self._log("Mining stopped.")

    def _parse_hashrate(self, line):
        # XMRig outputs lines like: "speed 10s/60s/15m 1234.5 567.8 890.1 H/s"
        m = re.search(r"(\d+\.?\d*)\s+H/s", line)
        if m:
            hs = m.group(1)
            self.after(0, lambda: self.hashrate_var.set(f"Hashrate: {hs} H/s"))
            self.after(0, lambda: self.status_var.set("Status: Mining ●"))

    def _set_mining_ui(self, active):
        if active:
            self.start_btn.config(state="disabled", bg="#555")
            self.stop_btn.config(state="normal",   bg="#e94560", fg="white")
            self.status_var.set("Status: Starting…")
        else:
            self.start_btn.config(state="normal",  bg="#e94560")
            self.stop_btn.config(state="disabled", bg="#333", fg="#aaa")

    def _log(self, msg):
        def _append():
            with self.log_lock:
                self.log_box.config(state="normal")
                self.log_box.insert("end", msg + "\n")
                self.log_box.see("end")
                self.log_box.config(state="disabled")
        self.after(0, _append)

    def _on_close(self):
        self._stop()
        time.sleep(0.3)
        self.destroy()


if __name__ == "__main__":
    app = XMRMinerApp()
    app.mainloop()
