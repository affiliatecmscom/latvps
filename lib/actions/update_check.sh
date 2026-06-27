#!/usr/bin/env bash
# actions/update_check.sh - kiểm tra có bản LATVPS mới hơn không (so VERSION local với repo).
# Dùng cho: thông báo lúc đăng nhập SSH (profile.d, chạy 'lat update-check --refresh' nền)
# và lệnh tay 'lat update-check'. KHÔNG tự cập nhật - chỉ báo + hướng dẫn 'lat update'.

# Nguồn VERSION mới nhất (repo public). Override được qua env nếu cần.
LATVPS_VERSION_URL="${LATVPS_VERSION_URL:-https://raw.githubusercontent.com/affiliatecmscom/latvps/main/VERSION}"
# Cache trạng thái (gitignore): LATEST=<ver> + CHECKED=<epoch>. profile.d đọc file này.
UPDATE_STATE_FILE="${WPF_ROOT}/.update-state"

# Lấy version mới nhất từ repo. In ra stdout nếu OK, rỗng nếu lỗi mạng.
remote_version() {
  curl -fsSL --max-time 8 "$LATVPS_VERSION_URL" 2>/dev/null | tr -d '[:space:]'
}

# So sánh semver: trả 0 nếu $1 > $2 (mới hơn), ngược lại 1.
version_gt() {
  [ "$1" = "$2" ] && return 1
  [ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | tail -n1)" = "$1" ]
}

# Ghi cache trạng thái (best-effort; cần quyền ghi WPF_ROOT).
_write_update_state() {
  printf 'LATEST=%s\nCHECKED=%s\n' "$1" "$(date +%s)" > "$UPDATE_STATE_FILE" 2>/dev/null || true
}

# act_update_check [--refresh]
#   (không cờ): hỏi repo, cập nhật cache, IN kết quả cho người dùng.
#   --refresh : chạy nền cho profile.d - chỉ cập nhật cache, im lặng.
act_update_check() {
  local mode="${1:-}"
  local cur latest
  cur="$(cat "${WPF_ROOT}/VERSION" 2>/dev/null || echo 0.0.0)"
  latest="$(remote_version)"

  if [ -z "$latest" ]; then
    [ "$mode" = "--refresh" ] && return 0   # nền: im lặng khi mạng lỗi
    warn "Không kiểm tra được bản mới (mạng/Github?)."
    return 1
  fi

  _write_update_state "$latest"

  if version_gt "$latest" "$cur"; then
    [ "$mode" = "--refresh" ] && return 0   # nền: profile.d sẽ in lúc đăng nhập
    ui_msg "Có bản LATVPS mới: ${cur} -> ${latest}\n\nCập nhật ngay:  lat update"
    return 0
  fi

  [ "$mode" = "--refresh" ] || ok "Đang dùng bản mới nhất (${cur})."
  return 0
}
