import json
import os
from datetime import datetime

src = r"c:\Users\Window10\Downloads\hospital_data (2).json"
dst = r"c:\xampp\htdocs\hospital_1\hospital\storage\jsondb\hospital.json"

with open(src, "r", encoding="utf-8") as f:
    payload = json.load(f)

records = payload.get("records") if isinstance(payload, dict) else None
if records is None and isinstance(payload, dict):
    records = payload.get("tables")
if records is None:
    records = payload if isinstance(payload, dict) else {}

tables = {}
for name in ["users", "doctors", "patients", "appointments", "prescriptions"]:
    rows = records.get(name, []) if isinstance(records, dict) else []
    if not isinstance(rows, list):
        rows = []
    normalized = []
    for row in rows:
        if not isinstance(row, dict):
            continue
        if "email" in row:
            row["email"] = str(row["email"]).lower()
        normalized.append(row)
    tables[name] = normalized

counters = {}
for name, rows in tables.items():
    counters[name] = max([int(r.get("id", 0)) for r in rows] or [0])

now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
data = {
    "meta": {
        "version": 1,
        "created_at": now,
        "updated_at": now,
        "imported_at": now,
        "source_file": src,
    },
    "counters": counters,
    "tables": tables,
}

os.makedirs(os.path.dirname(dst), exist_ok=True)
with open(dst, "w", encoding="utf-8") as f:
    json.dump(data, f, ensure_ascii=True, indent=4)

print(f"Imported JSON records into {dst}")
