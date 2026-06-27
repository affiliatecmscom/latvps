#!/usr/bin/env bash
# lib/ui.sh - lớp giao diện.
# CHỈ hỏi-đáp TỪNG DÒNG trong terminal (hợp SSH, học viên dễ dùng, không vỡ layout).
# KHÔNG dùng bảng TUI whiptail (box ở giữa hay vỡ/đen trên nhiều terminal -> bỏ hẳn).
# Mọi hàm trả KẾT QUẢ qua stdout (để $() bắt được), vẽ UI ra stderr/terminal.

HAS_WHIPTAIL=0  # luôn tắt: dùng hỏi-đáp từng dòng cho mọi bước.

# ui_menu "TITLE"  tag1 "label1"  tag2 "label2" ...  -> in tag được chọn. Return !=0 nếu Huỷ.
ui_menu() {
  local title="$1"; shift
  if [ "$HAS_WHIPTAIL" = 1 ]; then
    local n=$(( $# / 2 ))
    [ "$n" -gt 12 ] && n=12
    whiptail --title "lat" --notags --menu "$title" 20 74 "$n" "$@" 3>&1 1>&2 2>&3
    return $?
  fi
  # Fallback dòng: đánh SỐ từng lựa chọn, người dùng gõ số cho nhanh (không phải gõ tag dài).
  printf '\n== %s ==\n' "$title" >&2
  local items=("$@") i n=0 tags=()
  for ((i=0; i<${#items[@]}; i+=2)); do
    n=$((n+1)); tags+=("${items[i]}")
    printf '  %d) %s\n' "$n" "${items[i+1]}" >&2
  done
  local choice
  while true; do
    printf 'Chọn [1-%d]: ' "$n" >&2
    read -r choice </dev/tty || return 1
    if [[ "$choice" =~ ^[0-9]+$ ]] && [ "$choice" -ge 1 ] && [ "$choice" -le "$n" ]; then
      printf '%s' "${tags[choice-1]}"; return 0
    fi
    printf 'Nhập số từ 1 đến %d.\n' "$n" >&2
  done
}

# ui_input "PROMPT" "DEFAULT" -> in giá trị nhập (hoặc default). Return !=0 nếu Huỷ (whiptail).
ui_input() {
  local prompt="$1" def="${2:-}"
  if [ "$HAS_WHIPTAIL" = 1 ]; then
    whiptail --title "lat" --inputbox "$prompt" 11 74 "$def" 3>&1 1>&2 2>&3
    return $?
  fi
  printf '%b' "$prompt" >&2
  [ -n "$def" ] && printf ' [%s]' "$def" >&2
  printf ': ' >&2
  local v; read -r v </dev/tty || return 1
  printf '%s' "${v:-$def}"
}

# ui_yesno "MSG" -> return 0 nếu Yes, 1 nếu No.
# Dùng --menu (không dùng --yesno) vì: (1) một số terminal không vẽ được nút Yes/No
# của newt -> box trống, kẹt; (2) menu điều hướng bằng phím mũi tên - tự nhiên hơn.
ui_yesno() {
  local msg="$1"
  if [ "$HAS_WHIPTAIL" = 1 ]; then
    local ans
    ans="$(whiptail --title "lat" --notags --menu "$msg" 18 74 2 \
      yes "Có" no "Không" 3>&1 1>&2 2>&3)" || return 1
    [ "$ans" = "yes" ]
    return $?
  fi
  printf '%b' "$msg" >&2
  printf ' [y/N]: ' >&2
  local a; read -r a </dev/tty || return 1
  case "$a" in [Yy]*) return 0;; *) return 1;; esac
}

# ui_msg "TEXT" -> hiện thông báo.
ui_msg() {
  if [ "$HAS_WHIPTAIL" = 1 ]; then
    whiptail --title "lat" --scrolltext --msgbox "$1" 20 74
  else
    printf '\n' >&2; printf '%b' "$1" >&2; printf '\n' >&2
    printf '[Enter để tiếp tục] ' >&2; read -r _ </dev/tty || true
  fi
}

# ui_paste_block "PROMPT" "END_REGEX" -> đọc khối NHIỀU DÒNG từ tty (cho dán cert/key PEM),
# dừng khi gặp dòng khớp END_REGEX (gồm cả dòng đó). In khối ra stdout.
# Lý do không dùng inputbox whiptail: inputbox 1 dòng, dán PEM nhiều dòng bị submit sớm -> hỏng.
ui_paste_block() {
  local prompt="$1" endre="$2" line buf=""
  # Thoát hẳn TUI: hướng dẫn in ra stderr, đọc thô từ /dev/tty theo dòng.
  printf '\n----------------------------------------\n' >&2
  printf '%s\n' "$prompt" >&2
  printf '(Dán nguyên khối; tự kết thúc khi gặp dòng END.)\n' >&2
  while IFS= read -r line </dev/tty; do
    buf+="$line"$'\n'
    [[ "$line" =~ $endre ]] && break
  done
  printf '%s' "$buf"
}

# ui_gauge_msg "TEXT" - thông báo tiến trình ngắn (không chặn). Dùng info() là đủ; alias cho rõ nghĩa.
ui_note() { info "$@"; }
