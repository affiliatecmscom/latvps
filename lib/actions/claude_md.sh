#!/usr/bin/env bash
# actions/claude_md.sh - tạo CLAUDE.md ở thư mục gốc site để Claude Code tự đọc luật dự án.
# Khuôn: templates/claude/CLAUDE.md.tmpl. Đặt ở ${SITES_ROOT}/<id>/ (KHÔNG phải wp-content/:
# nginx chỉ phục vụ wp-content và core, nên file ở thư mục gốc site không lộ ra web).

CLAUDE_MD_TMPL="${WPF_ROOT}/templates/claude/CLAUDE.md.tmpl"

# Tên brand gợi ý từ domain: "best-gear.com" -> "Best Gear".
_claude_md_brand_default() {
  printf '%s' "${1%%.*}" | tr '-' ' ' | awk '{for(i=1;i<=NF;i++) $i=toupper(substr($i,1,1)) substr($i,2)} 1'
}

# claude_md_write <id> <domain> <brand> [niche] -> ghi ${dir}/CLAUDE.md (file cũ đổi tên .bak).
# Điền placeholder lat biết được; phần còn lại giữ {{...}} cho học viên tự điền.
claude_md_write() {
  local id="$1" domain="$2" brand="$3" niche="${4:-}" dir theme out
  dir="$(site_dir "$id")"; out="${dir}/CLAUDE.md"
  [ -f "$CLAUDE_MD_TMPL" ] || { warn "Thiếu khuôn ${CLAUDE_MD_TMPL}."; return 1; }
  [ -d "$dir" ] || { warn "Không thấy thư mục site ${dir}."; return 1; }
  theme="$(wp_run "$id" option get stylesheet 2>/dev/null | tr -d '[:space:]')"
  [ -f "$out" ] && cp -p "$out" "${out}.$(date +%Y%m%d-%H%M%S).bak"
  # Thay qua ENVIRON của awk: brand/niche người nhập có ký tự lạ (&, /, \) cũng không vỡ như sed.
  # Mục 7 là danh sách placeholder để học viên đối chiếu, giữ nguyên.
  BRAND="$brand" DOMAIN="$domain" NICHE="$niche" SITE_ID="$id" SITE_DIR="$dir" \
  THEME="${theme:-(chưa rõ)}" TODAY="$(date +%Y-%m-%d)" awk '
    function rep(s, k, v,   i, r) {
      r = ""
      while ((i = index(s, k)) > 0) { r = r substr(s, 1, i - 1) v; s = substr(s, i + length(k)) }
      return r s
    }
    /^## 7\./ { keep = 1 }
    !keep {
      $0 = rep($0, "{{BRAND}}", ENVIRON["BRAND"]); $0 = rep($0, "{{DOMAIN}}", ENVIRON["DOMAIN"])
      $0 = rep($0, "{{SITE_ID}}", ENVIRON["SITE_ID"]); $0 = rep($0, "{{SITE_DIR}}", ENVIRON["SITE_DIR"])
      $0 = rep($0, "{{THEME}}", ENVIRON["THEME"]); $0 = rep($0, "{{YYYY-MM-DD}}", ENVIRON["TODAY"])
      if (ENVIRON["NICHE"] != "") $0 = rep($0, "{{NICHE}}", ENVIRON["NICHE"])
    }
    { print }
  ' "$CLAUDE_MD_TMPL" > "$out" || { warn "Ghi ${out} lỗi."; return 1; }
  chmod 644 "$out"
  ok "Đã tạo ${out}"
}

# Hỏi brand + ngách rồi ghi. Dùng chung cho lat add và lat claude-md.
claude_md_prompt_write() {
  local id="$1" domain="$2" brand niche
  brand="$(ui_input "Tên brand cho CLAUDE.md:" "$(_claude_md_brand_default "$domain")")" || return 1
  [ -n "$brand" ] || brand="$(_claude_md_brand_default "$domain")"
  niche="$(ui_input "Ngách (vd: kitchen appliances). Để trống thì điền sau trong file:" "")" || niche=""
  claude_md_write "$id" "$domain" "$brand" "$niche"
}

# lat claude-md [id|domain] - tạo/làm lại CLAUDE.md cho site đã cài.
act_claude_md() {
  require_root
  local arg="${1:-}" id dom
  if [ -z "$arg" ]; then
    local ids; ids="$(list_site_ids)"
    [ -n "$ids" ] || { warn "Chưa có site nào."; return 1; }
    local items=() i
    for i in $ids; do items+=("$i" "$(site_get "$i" DOMAIN)"); done
    id="$(ui_menu "Tạo CLAUDE.md cho site nào?" "${items[@]}")" || return 0
  else
    id="$(resolve_site "$arg")" || { warn "Không tìm thấy site: $arg"; return 1; }
  fi
  dom="$(site_get "$id" DOMAIN)"
  if [ -f "$(site_dir "$id")/CLAUDE.md" ]; then
    ui_yesno "Site ${dom} đã có CLAUDE.md. Tạo lại từ mẫu? (bản cũ được giữ thành .bak)" || return 0
  fi
  claude_md_prompt_write "$id" "$dom" || return 1
  ui_msg "Dùng Claude Code cho site này:\n\n  cd $(site_dir "$id") && claude\n\nClaude tự đọc CLAUDE.md mỗi phiên. Mở file, điền nốt các chỗ {{...}}:\n  grep -n \"{{\" $(site_dir "$id")/CLAUDE.md"
}
