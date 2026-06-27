# /etc/profile.d/latvps-update.sh
# Thông báo khi đăng nhập SSH nếu LATVPS có bản mới. Cài bởi 'lat setup'.
# Nhẹ + an toàn: chỉ đọc cache, không gọi mạng đồng bộ (refresh chạy NỀN khi cache cũ).
# Sourced bởi /etc/profile -> dùng hàm + return, không exit.

__latvps_login_notice() {
  # Chỉ chạy cho shell tương tác có terminal (bỏ qua scp/rsync/cron).
  case $- in *i*) ;; *) return 0 ;; esac
  [ -t 1 ] || return 0

  # Dò gốc cài đặt từ symlink 'lat' (giống bin/lat) -> không hardcode đường dẫn.
  local lat_bin real root
  lat_bin="$(command -v lat 2>/dev/null)" || return 0
  real="$(readlink -f "$lat_bin" 2>/dev/null || echo "$lat_bin")"
  root="$(cd "$(dirname "$real")/.." 2>/dev/null && pwd)" || return 0
  [ -r "${root}/VERSION" ] || return 0

  local cur latest checked now state stale=21600   # 6 giờ
  cur="$(cat "${root}/VERSION" 2>/dev/null)"
  state="${root}/.update-state"
  if [ -r "$state" ]; then
    latest="$(sed -n 's/^LATEST=//p' "$state" 2>/dev/null | head -n1)"
    checked="$(sed -n 's/^CHECKED=//p' "$state" 2>/dev/null | head -n1)"
  fi
  now="$(date +%s 2>/dev/null)"

  # Cache thiếu/cũ -> refresh NỀN (không chặn login). Lần đăng nhập sau mới hiện.
  case "$checked" in
    ''|*[!0-9]*) ( lat update-check --refresh >/dev/null 2>&1 & ) 2>/dev/null ;;
    *) [ $(( now - checked )) -ge "$stale" ] && ( lat update-check --refresh >/dev/null 2>&1 & ) 2>/dev/null ;;
  esac

  # Có bản mới hơn? (so semver bằng sort -V để chắc latest thực sự mới hơn cur)
  [ -n "$latest" ] && [ -n "$cur" ] && [ "$latest" != "$cur" ] || return 0
  [ "$(printf '%s\n%s\n' "$cur" "$latest" | sort -V | tail -n1)" = "$latest" ] || return 0

  printf '\n\033[1;33m[LATVPS]\033[0m Có bản cập nhật: %s -> \033[1;32m%s\033[0m\n' "$cur" "$latest"
  printf '         Chạy \033[1;36mlat update\033[0m để cập nhật bộ lệnh.\n\n'
}
__latvps_login_notice
unset -f __latvps_login_notice
