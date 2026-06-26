#!/usr/bin/env bash
# iflmmo.sh — BOOTSTRAP 1 lệnh cho VPS Ubuntu trắng.
# Dùng:  curl -fsSL https://cdn.lat.vn/iflmmo.sh | sudo bash
# Kéo bộ wp-factory về /opt/wp-factory rồi chạy setup (Docker/UFW/Caddy/license/symlink iflmmo).
set -euo pipefail

# Nguồn code (đặt repo thật khi phát hành; có thể override bằng biến môi trường).
IFLMMO_REPO="${IFLMMO_REPO:-https://github.com/affiliatecmscom/wp-factory.git}"
DEST="/opt/wp-factory"

[ "$(id -u)" -eq 0 ] || { echo "Vui lòng chạy bằng root (sudo)."; exit 1; }

echo "[*] WP Factory bootstrap"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git curl ca-certificates >/dev/null

if [ -x "${DEST}/bin/iflmmo" ]; then
  echo "[OK] Đã có ${DEST} — dùng bản hiện tại."
elif [ -d "${DEST}/.git" ]; then
  echo "[*] Cập nhật ${DEST}..."
  git -C "$DEST" pull --ff-only || true
else
  echo "[*] Tải code về ${DEST}..."
  git clone --depth 1 "$IFLMMO_REPO" "$DEST"
fi

chmod +x "${DEST}/bin/iflmmo"
echo "[*] Chạy setup host..."
exec "${DEST}/bin/iflmmo" setup
