"""Build a tag-versioned package; verify the same release before deployment."""
import argparse
import json
import os
from pathlib import Path
import re
import zipfile

ROOT_FILES = {"opencart-module.json", "install.xml", "index.xml", "install.php", "uninstall.php"}
DEVELOPMENT_NAMES = {".git", ".github", ".idea", ".vscode", ".ocm", "__pycache__",
                     ".DS_Store", "Thumbs.db", ".gitignore", ".gitattributes", ".ocm_files.json"}


def allowed_entry(name):
    path = Path(name)
    if path.is_absolute() or ".." in path.parts or "\\" in name:
        return False
    if len(path.parts) == 1:
        return name in ROOT_FILES
    if path.parts[0] != "upload":
        return False
    return not any(part in DEVELOPMENT_NAMES or part == ".env" or part.startswith(".env.")
                   or part.endswith((".pyc", ".swp", ".swo", "~")) for part in path.parts[1:])

parser = argparse.ArgumentParser()
parser.add_argument("--verify-artifact", type=Path)
parser.add_argument("--sync-only", action="store_true")
parser.add_argument("--strict", action="store_true")
args = parser.parse_args()
tag = os.environ["RELEASE_TAG"]
if not re.fullmatch(r"v?\d+(?:\.\d+)*(?:[-+][A-Za-z0-9.-]+)?", tag):
    raise SystemExit("Release tag must be a numeric version, optionally prefixed with v")
version = tag.removeprefix("v")
if len(version) > 32:
    raise SystemExit("Version exceeds the server's 32-character limit")
manifest = Path("opencart-module.json")
metadata = json.loads(manifest.read_text())
code = metadata.get("code", "")
name = metadata.get("module_name") or metadata.get("name")
author = metadata.get("creator_name") or metadata.get("author")
if not isinstance(code, str) or not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_-]{0,63}", code):
    raise SystemExit("Invalid module code in opencart-module.json")
if not all(isinstance(value, str) and value.strip() and "\n" not in value and "\r" not in value
           for value in (name, author)):
    raise SystemExit("Module name and author must be nonempty single-line strings")
if metadata.get("version") != version:
    if args.strict:
        raise SystemExit("Manifest version differs from the release tag")
    print(f"Updating manifest version: {metadata.get('version')} -> {version}")
metadata["version"] = version
if not args.strict:
    manifest.write_text(json.dumps(metadata, ensure_ascii=False, indent=4) + "\n")
if args.sync_only:
    raise SystemExit(0)
archive_name = f"{code}_{version}.ocmod.zip"
if args.verify_artifact:
    archives = list(args.verify_artifact.glob("*.ocmod.zip"))
    if len(archives) != 1 or archives[0].name != archive_name:
        raise SystemExit("Build artifact does not match this tag/module")
    with zipfile.ZipFile(archives[0]) as archive:
        if any(not allowed_entry(name) for name in archive.namelist()):
            raise SystemExit("Build artifact contains files outside the package allowlist")
        if json.loads(archive.read("opencart-module.json")) != metadata:
            raise SystemExit("Build artifact metadata does not match this tag/module")
    archive_path = archives[0]
else:
    upload = Path("upload")
    if not upload.is_dir():
        raise SystemExit("upload/ directory is missing")
    archive_path = Path("dist") / archive_name
    archive_path.parent.mkdir(exist_ok=True)
    for path in upload.rglob("*"):
        if path.is_symlink():
            raise SystemExit(f"Symlinks are not allowed in release packages: {path}")
    files = [manifest] + sorted(path for path in upload.rglob("*")
                                if path.is_file() and allowed_entry(path.as_posix()))
    files += [Path(filename) for filename in sorted(ROOT_FILES - {manifest.name})
              if Path(filename).is_file()]
    with zipfile.ZipFile(archive_path, "w", zipfile.ZIP_DEFLATED) as archive:
        for path in files:
            if path.is_symlink():
                raise SystemExit(f"Symlinks are not allowed in release packages: {path}")
            archive.write(path, path.as_posix())
if os.environ.get("GITHUB_ENV"):
    with open(os.environ["GITHUB_ENV"], "a") as output:
        for key, value in {"ARCHIVE_PATH": str(archive_path), "MODULE_CODE": code,
                           "MODULE_VERSION": version, "MODULE_NAME": name,
                           "MODULE_AUTHOR": author}.items():
            output.write(f"{key}={value}\n")
print(f"Package ready: {archive_path}")
