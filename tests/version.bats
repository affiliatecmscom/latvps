#!/usr/bin/env bats
# version_gt quyết định việc BÁO BẢN MỚI và việc cron AI dùng header hay query string
# (cron.sh: version_gt "1.3.23" "$ai_ver"). Sai ở đây = cron 403 âm thầm.

load helper
setup() { load_lat; }

@test "version_gt: mới hơn -> 0" {
  version_gt "3.18.0" "3.17.2"
  version_gt "3.17.10" "3.17.9"
  version_gt "1.3.23" "1.3.9"
}

@test "version_gt: bằng nhau -> khác 0" {
  run version_gt "3.17.2" "3.17.2"
  [ "$status" -ne 0 ]
}

@test "version_gt: cũ hơn -> khác 0" {
  run version_gt "3.17.2" "3.18.0"
  [ "$status" -ne 0 ]
  run version_gt "1.3.9" "1.3.23"
  [ "$status" -ne 0 ]
}

@test "cron AI: plugin >= 1.3.23 thì dùng header (không lộ cron_key vào access log)" {
  # Lặp đúng điều kiện ở lib/actions/cron.sh
  for ver in 1.3.23 1.3.24 1.4.0 2.0.0; do
    run version_gt "1.3.23" "$ver"
    [ "$status" -ne 0 ] || { echo "ver $ver bị coi là cũ -> rơi về query string"; false; }
  done
}

@test "cron AI: plugin < 1.3.23 thì phải rơi về query string" {
  for ver in 1.3.22 1.3.9 1.2.0; do
    run version_gt "1.3.23" "$ver"
    [ "$status" -eq 0 ] || { echo "ver $ver bị coi là mới -> cron sẽ 403"; false; }
  done
}
