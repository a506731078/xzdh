#!/usr/bin/env python3
import json
import os
import shutil
import sys
import time
import zipfile
from pathlib import Path

try:
    import resource
except ImportError:  # pragma: no cover
    resource = None

try:
    from tqdm import tqdm  # noqa: F401
except Exception:  # pragma: no cover
    tqdm = None


def emit(payload):
    sys.stdout.write(json.dumps(payload, ensure_ascii=False) + "\n")
    sys.stdout.flush()


def task_file(project_root: Path, task_id: str) -> Path:
    return project_root / "data" / "tasks" / f"{task_id}.json"


def update_task(task_path: Path, patch: dict):
    current = {}
    if task_path.exists():
        try:
            current = json.loads(task_path.read_text(encoding="utf-8"))
        except Exception:
            current = {}
    current.update(patch)
    current["updated_at"] = int(time.time())
    task_path.write_text(json.dumps(current, ensure_ascii=False, indent=2), encoding="utf-8")


def set_memory_limit(memory_limit_mb: int):
    if resource is None or memory_limit_mb <= 0:
        return
    memory_bytes = memory_limit_mb * 1024 * 1024
    resource.setrlimit(resource.RLIMIT_AS, (memory_bytes, memory_bytes))


def collect_entries(source: Path):
    entries = []
    total_size = 0
    blocked_ext = {".php", ".phtml", ".php3", ".php4", ".php5", ".py", ".htaccess", ".env", ".ini", ".sh"}
    blocked_names = {".htaccess", ".env", ".user.ini", "web.config"}

    for root, dirs, files in os.walk(source):
        root_path = Path(root)
        dirs[:] = [d for d in dirs if not (root_path / d).is_symlink()]

        rel_root = root_path.relative_to(source)
        if rel_root != Path("."):
            entries.append((root_path, True, 0))

        for filename in files:
            file_path = root_path / filename
            if file_path.is_symlink():
                continue
            if file_path.name.lower() in blocked_names or file_path.suffix.lower() in blocked_ext:
                continue
            size = file_path.stat().st_size
            total_size += size
            entries.append((file_path, False, size))

    return entries, total_size


def format_speed(bytes_per_sec: float) -> str:
    if bytes_per_sec <= 0:
        return "0 MB/s"
    return f"{bytes_per_sec / 1024 / 1024:.2f} MB/s"


def main():
    if len(sys.argv) != 2:
        emit({"percent": 0, "error": "missing_payload"})
        return 1

    payload = json.loads(sys.argv[1])
    source = Path(payload["source"]).resolve()
    target = Path(payload["target"]).resolve()
    task_id = payload["task_id"]
    compresslevel = int(payload.get("compresslevel", 6))
    memory_limit_mb = int(payload.get("memory_limit_mb", 512))
    project_root = Path(__file__).resolve().parent.parent
    task_path = task_file(project_root, task_id)

    set_memory_limit(memory_limit_mb)

    if not source.exists() or not source.is_dir():
        update_task(task_path, {"status": "error", "error": "源目录不存在"})
        emit({"percent": 0, "error": "source_not_found"})
        return 2

    target.parent.mkdir(parents=True, exist_ok=True)
    temp_target = target.with_suffix(target.suffix + ".part")
    if temp_target.exists():
        temp_target.unlink()

    entries, total_size = collect_entries(source)
    if not entries:
        update_task(task_path, {"status": "error", "error": "目录为空"})
        emit({"percent": 0, "error": "empty_dir"})
        return 3

    processed = 0
    last_emit = 0.0
    started_at = time.time()

    update_task(task_path, {
        "status": "running",
        "percent": 0,
        "speed": "0 MB/s",
        "remaining": "--",
        "error": "",
        "worker_started": True,
        "worker_pid": os.getpid(),
    })
    emit({"percent": 0, "speed": "0 MB/s", "remaining": "--"})

    try:
        with zipfile.ZipFile(
            temp_target,
            mode="w",
            compression=zipfile.ZIP_DEFLATED,
            compresslevel=compresslevel,
            allowZip64=True,
        ) as zf:
            for entry, is_dir, size in entries:
                rel_name = entry.relative_to(source).as_posix()
                if is_dir:
                    zf.writestr(rel_name.rstrip("/") + "/", "")
                else:
                    zf.write(entry, rel_name)
                    processed += size

                now = time.time()
                if now - last_emit >= 0.2:
                    elapsed = max(now - started_at, 0.001)
                    speed = processed / elapsed
                    remaining = 0 if total_size == 0 else max(total_size - processed, 0) / max(speed, 1)
                    percent = 100 if total_size == 0 else min(99, round(processed / total_size * 100))
                    payload = {
                        "percent": percent,
                        "speed": format_speed(speed),
                        "remaining": f"{int(remaining)} s",
                    }
                    emit(payload)
                    update_task(task_path, payload)
                    last_emit = now

        shutil.move(str(temp_target), str(target))
        final_payload = {
            "status": "done",
            "percent": 100,
            "speed": format_speed(processed / max(time.time() - started_at, 0.001)),
            "remaining": "0 s",
            "zip_path": str(target),
        }
        update_task(task_path, final_payload)
        emit({"percent": 100, "speed": final_payload["speed"], "remaining": "0 s"})
        return 0
    except Exception as exc:
        if temp_target.exists():
            temp_target.unlink()
        update_task(task_path, {"status": "error", "error": str(exc)})
        emit({"percent": 0, "error": str(exc)})
        return 4


if __name__ == "__main__":
    raise SystemExit(main())
