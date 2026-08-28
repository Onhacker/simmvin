#!/bin/sh

set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/.." && pwd)
cd "$project_dir"

css_minifier='clean-css-cli@5.6.3'
js_minifier='terser@5.31.6'

minify_css() {
    source_file=$1
    output_file=$2
    npm exec --yes --package="$css_minifier" -- cleancss -O1 -o "$output_file" "$source_file"
}

minify_js() {
    source_file=$1
    output_file=$2
    npm exec --yes --package="$js_minifier" -- terser "$source_file" --compress --mangle -o "$output_file"
    node --check "$output_file"
}

minify_css assets/css/simp-v22.css assets/css/simp-v22.min.css
minify_css assets/v22/styles/bootstrap.css assets/v22/styles/bootstrap.min.css
minify_css assets/appkit/styles/bootstrap.css assets/appkit/styles/bootstrap.min.css
minify_css template/styles/bootstrap.css template/styles/bootstrap.min.css

for source_file in assets/js/*.js; do
    case "$source_file" in
        *.min.js) continue ;;
    esac
    minify_js "$source_file" "${source_file%.js}.min.js"
done

minify_js assets/v22/scripts/custom.js assets/v22/scripts/custom.min.js
minify_js template/scripts/custom.js template/scripts/custom.min.js
minify_js service-worker.js service-worker.min.js

printf '%s\n' 'Aset CSS dan JavaScript minified berhasil diperbarui.'
