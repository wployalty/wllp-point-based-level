#!/bin/bash
echo "WPLoyalty - Points based level"
current_dir="$PWD"
plugin_name="wllp-point-based-level"
pack_folder=$current_dir"/../compressed_pack"
plugin_compress_folder=$pack_folder"/"$plugin_name
composer_run() {
  # shellcheck disable=SC2164
  cd "$current_dir"
  composer install --no-dev
  composer update --no-dev
  cd ..
  echo "Compress Done"
  # shellcheck disable=SC2164
  cd "$current_dir"
}
copy_folder() {
  if [ -d "$pack_folder" ]; then
    rm -r "$pack_folder"
  fi
  mkdir "$pack_folder"
  mkdir "$plugin_compress_folder"
  move_dir=("App" "Assets" "vendor" "i18n" $plugin_name".php")
  # shellcheck disable=SC2068
  for dir in ${move_dir[@]}; do
    cp -r "$current_dir/$dir" "$plugin_compress_folder/$dir"
  done
}
zip_folder() {
  cd "$pack_folder"
  rm "$plugin_name".zip
  zip -r "$plugin_name".zip $plugin_name -q
  zip -d "$plugin_name".zip __MACOSX/\*
  zip -d "$plugin_name".zip \*/.DS_Store
}
echo "Composer Run:"
composer_run
copy_folder
zip_folder