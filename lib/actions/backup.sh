#!/usr/bin/env bash
# actions/backup.sh - backup/restore site (db.sql + wp-content + config).

BACKUP_KEEP="${BACKUP_KEEP:-14}"

# backup 1 site theo id. In đường dẫn file ra stdout.
_backup_one() {
  local id="$1"
  local dir; dir="$(site_dir "$id")"
  local domain; domain="$(site_get "$id" DOMAIN)"
  local out="${BACKUPS_ROOT}/${id}"
  mkdir -p "$out"
  # Backup chứa .env (DB/Redis pass) + dump DB -> chỉ root đọc.
  chmod 700 "$BACKUPS_ROOT" "$out" 2>/dev/null || true
  local stamp; stamp="$(date +%Y%m%d-%H%M%S)"
  local tmp="${dir}/db.sql"

  info "Backup ${domain} (${id})..."
  ( umask 077; docker exec "${id}_db" sh -c 'exec mariadb-dump --no-tablespaces -uwordpress -p"$MARIADB_PASSWORD" wordpress' > "$tmp" 2>/dev/null ) \
    || { warn "Dump DB lỗi cho ${id}."; rm -f "$tmp"; return 1; }
  chmod 600 "$tmp" 2>/dev/null || true

  local file="${out}/${stamp}.tar.gz"
  # PHẢI kiểm exit code của tar: bỏ qua = báo "Đã backup" cho file rỗng/hỏng (vd hết disk),
  # rồi file rác đó vẫn chiếm 1 slot xoay vòng -> vài ngày sau xoá sạch bản backup CÒN TỐT.
  if ! ( umask 077; tar -C "$SITES_ROOT" -czf "$file" "$id" 2>/dev/null ); then
    warn "Đóng gói backup lỗi cho ${id} (hết dung lượng?). Kiểm tra: df -h"
    rm -f "$file" "$tmp"
    return 1
  fi
  chmod 600 "$file" 2>/dev/null || true
  rm -f "$tmp"

  # xoay vòng. `|| true`: glob không khớp -> ls trả 1 -> pipefail+set -e giết cả lat không thông báo.
  ls -1t "${out}"/*.tar.gz 2>/dev/null | tail -n +"$((BACKUP_KEEP+1))" | xargs -r rm -f || true
  ok "Đã backup: ${file}"
  printf '%s' "$file"
}

# act_backup [id|domain|all]
act_backup() {
  require_root
  local arg="${1:-all}"
  if [ "$arg" = "all" ]; then
    local id any=0
    for id in $(list_site_ids); do _backup_one "$id" >/dev/null && any=1; done
    [ "$any" = 1 ] && ok "Backup tất cả xong." || info "Không có site để backup."
    return 0
  fi
  local id; id="$(resolve_site "$arg")" || { warn "Không tìm thấy site: $arg"; return 1; }
  _backup_one "$id" >/dev/null
}

# act_restore <id|domain> <file.tar.gz>
act_restore() {
  require_root
  local arg="${1:-}" file="${2:-}"
  [ -n "$arg" ] && [ -n "$file" ] || { warn "Dùng: lat restore <id|domain> <file.tar.gz>"; return 1; }
  [ -f "$file" ] || { warn "Không thấy file: $file"; return 1; }
  local id; id="$(resolve_site "$arg")" || { warn "Không tìm thấy site: $arg"; return 1; }
  local dir; dir="$(site_dir "$id")"

  # Tar đóng gói theo ID (`tar ... "$id"`) nên nó LUÔN giải nén vào đúng ID nhúng bên trong,
  # bất kể tham số <id|domain> ở trên. Không kiểm tra = `lat restore siteB backupA.tar.gz` sẽ
  # hồi sinh site A (kể cả A đã xoá) và KHÔNG đụng gì tới B, nhưng vẫn báo "Phục hồi xong B".
  local tar_id; tar_id="$(tar -tzf "$file" 2>/dev/null | head -1 | cut -d/ -f1)"
  if [ -z "$tar_id" ]; then
    warn "Không đọc được nội dung ${file} (file hỏng?)."; return 1
  fi
  if [ "$tar_id" != "$id" ]; then
    warn "File backup này là của site '${tar_id}', không phải '${id}'."
    warn "Chạy đúng site: lat restore ${tar_id} ${file}"
    return 1
  fi

  ui_yesno "Phục hồi site ${id} từ ${file}? Dữ liệu hiện tại sẽ bị ghi đè." || return 1

  info "Giải nén wp-content + config..."
  tar -C "$SITES_ROOT" -xzf "$file" 2>/dev/null || { warn "Giải nén lỗi."; return 1; }
  docker compose -f "$dir/docker-compose.yml" --env-file "$dir/.env" up -d
  # Lấy pass từ .env vừa khôi phục: thiếu tham số này trước đây làm restore chết giữa chừng
  # (sau khi đã ghi đè file, trước khi import DB -> site còn file mới + database CŨ).
  local db_pass; db_pass="$(sed -n 's/^DB_PASSWORD=//p' "$dir/.env" 2>/dev/null | head -n1)"
  wait_for_db "${id}_db" "$db_pass" || { warn "DB không lên - site đang ở trạng thái dở. Chạy lại: lat restore ${id} ${file}"; return 1; }
  if [ -f "${dir}/db.sql" ]; then
    info "Import database..."
    docker exec -i "${id}_db" sh -c 'exec mariadb -uwordpress -p"$MARIADB_PASSWORD" wordpress' < "${dir}/db.sql" \
      && ok "Đã import DB." || warn "Import DB lỗi."
    rm -f "${dir}/db.sql"
  fi
  ok "Phục hồi xong site ${id}."
}
