#!/usr/bin/env bash
# Usage: scripts/optimize-anatomy-models.sh /path/to/original-glbs
# Originals are retained in Git history (see docs/anatomy-3d.md).
set -euo pipefail
source_dir="${1:?Pass the directory containing the ORIGINAL Hund.glb, katze.glb and Pferd.glb}"
repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT
for model in Hund katze Pferd; do
    npx --yes --package=@gltf-transform/cli@4.5.1 gltf-transform optimize \
        "$source_dir/$model.glb" "$work_dir/$model.glb" \
        --compress meshopt --simplify-ratio 0.12 --simplify-error 0.001 --texture-size 2048
done
# Only replace assets after all three conversions have succeeded.
for model in Hund katze Pferd; do
    cp "$work_dir/$model.glb" "$repo_dir/public/assets/3D/$model.glb"
    cp "$work_dir/$model.glb" "$repo_dir/flutter_app/assets/3d/models/$model.glb"
done
