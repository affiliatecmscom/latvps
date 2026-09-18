#!/usr/bin/env bash
# latvps.sh - BOOTSTRAP 1 lệnh cho VPS Ubuntu trắng.
#
# Cài / cập nhật bản mới nhất:
#   curl -fsSL https://raw.githubusercontent.com/affiliatecmscom/latvps/main/latvps.sh | sudo bash
#
# GHIM một bản cụ thể (khi bản main mới có lỗi -> quay về bản ổn định):
#   LATVPS_REF=v3.17.2 curl -fsSL https://raw.githubusercontent.com/affiliatecmscom/latvps/main/latvps.sh | sudo bash
#
# Kéo bộ latvps về /opt/latvps rồi chạy setup (Docker/UFW/proxy/license/symlink lat).
set -euo pipefail

# Nguồn code (override được bằng biến môi trường).
LATVPS_REPO="${LATVPS_REPO:-https://github.com/affiliatecmscom/latvps.git}"
# Nhánh HOẶC tag muốn cài. Mặc định 'main' = bản mới nhất.
LATVPS_REF="${LATVPS_REF:-main}"
DEST="/opt/latvps"

[ "$(id -u)" -eq 0 ] || { echo "Vui lòng chạy bằng root (sudo)."; exit 1; }

echo "[*] LATVPS bootstrap (ref: ${LATVPS_REF})"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git curl ca-certificates >/dev/null

# Đưa repo sẵn có về đúng LATVPS_REF.
# LATVPS_REF là NHÁNH -> checkout -B để bám nhánh (giữ cho `lat update` = git pull chạy được).
# LATVPS_REF là TAG   -> checkout detached, CỐ Ý: bản đã ghim thì `lat update` không được kéo đi.
_sync_to_ref() {
  git -C "$DEST" fetch --tags --prune --depth 1 origin "$LATVPS_REF" >/dev/null 2>&1 \
    || git -C "$DEST" fetch --tags --prune origin >/dev/null 2>&1 \
    || return 1
  if git -C "$DEST" show-ref --verify --quiet "refs/remotes/origin/${LATVPS_REF}"; then
    git -C "$DEST" checkout -q -B "$LATVPS_REF" "origin/${LATVPS_REF}"
  else
    git -C "$DEST" checkout -q "$LATVPS_REF" 2>/dev/null \
      || git -C "$DEST" checkout -q FETCH_HEAD 2>/dev/null \
      || return 1
  fi
}

if [ -d "${DEST}/.git" ]; then
  # BẪY ĐÃ SỬA (v3.18.0): bản cũ thấy bin/lat tồn tại là in "[OK] dùng bản hiện tại" rồi BỎ QUA
  # cập nhật. Học viên gõ lại lệnh curl để tự sửa máy đang hỏng -> không có gì thay đổi, mà vẫn
  # thấy [OK] nên tưởng đã fix. Giờ luôn đồng bộ về LATVPS_REF.
  if [ -n "$(git -C "$DEST" status --porcelain 2>/dev/null)" ]; then
    echo "[!] ${DEST} có thay đổi chưa commit - KHÔNG tự cập nhật (tránh mất code sửa tay)."
    echo "    Xem: git -C ${DEST} status   |   Bỏ thay đổi: git -C ${DEST} reset --hard"
  else
    echo "[*] Đồng bộ ${DEST} về '${LATVPS_REF}'..."
    _sync_to_ref && echo "[OK] Đã ở bản $(cat "${DEST}/VERSION" 2>/dev/null || echo '?')." \
      || echo "[!] Không đồng bộ được '${LATVPS_REF}' (mạng? ref không tồn tại?) - giữ bản hiện tại."
  fi
elif [ -e "$DEST" ]; then
  echo "[!] ${DEST} tồn tại nhưng KHÔNG phải git repo - giữ nguyên, không đụng vào."
  echo "    Muốn cài lại sạch: mv ${DEST} ${DEST}.bak  rồi chạy lại lệnh này."
else
  echo "[*] Tải code về ${DEST}..."
  # --branch nhận CẢ nhánh lẫn tag.
  git clone --depth 1 --branch "$LATVPS_REF" "$LATVPS_REPO" "$DEST" \
    || { git clone "$LATVPS_REPO" "$DEST" && git -C "$DEST" checkout "$LATVPS_REF"; }
fi

[ -x "${DEST}/bin/lat" ] || chmod +x "${DEST}/bin/lat"
echo "[*] Chạy setup host..."
exec "${DEST}/bin/lat" setup
