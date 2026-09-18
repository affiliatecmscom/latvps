#!/usr/bin/env bash
# actions/status.sh - tổng quan hệ thống (text).

status_text() {
  local total=0 aff=0 van=0 up=0 id typ st sick="" svc h
  for id in $(list_site_ids); do
    total=$((total+1))
    typ="$(site_get "$id" TYPE)"
    [ "$typ" = "vanilla" ] && van=$((van+1)) || aff=$((aff+1))
    st="$(docker inspect -f '{{.State.Status}}' "${id}_web" 2>/dev/null || echo down)"
    [ "$st" = "running" ] && up=$((up+1))
    # Liệt kê service có healthcheck đang báo hỏng (chỉ db/redis mới có healthcheck).
    for svc in db redis; do
      h="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{end}}' "${id}_${svc}" 2>/dev/null)"
      case "$h" in
        ""|healthy|starting) ;;
        *) sick="${sick}  - $(site_get "$id" DOMAIN) (${id}): ${svc} = ${h}"$'\n' ;;
      esac
    done
  done

  local proxy_st; proxy_st="$(docker inspect -f '{{.State.Status}}' latvps_proxy 2>/dev/null || echo down)"
  local mem; mem="$(free -h 2>/dev/null | awk '/Mem:/{print $3" / "$2}')"
  local disk; disk="$(df -h / 2>/dev/null | awk 'NR==2{print $3" / "$2" ("$5")"}')"

  printf 'LATVPS - trạng thái\n\n'
  printf 'Site        : %s (AffiliateCMS: %s, vanilla: %s)\n' "$total" "$aff" "$van"
  printf 'Đang chạy   : %s/%s\n' "$up" "$total"
  printf 'Proxy       : %s\n' "$proxy_st"
  printf 'RAM dùng    : %s\n' "${mem:-?}"
  printf 'Disk /      : %s\n' "${disk:-?}"
  if [ -n "$sick" ]; then
    printf '\nCẢNH BÁO - service không khoẻ:\n%s' "$sick"
    printf 'Xem log:  lat logs <domain> db   |   Khởi động lại: lat -> Quản lý site -> Bật/Tắt\n'
  fi
  printf '\n%s\n' "$(license_summary)"
}

act_status() {
  if [ "$HAS_WHIPTAIL" = 1 ] && [ -t 1 ]; then
    ui_msg "$(status_text)"
  else
    status_text
  fi
}
