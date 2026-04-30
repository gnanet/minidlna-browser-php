#!/bin/bash
#
# IMPORTANT NOTE
# To avoid using double scheme, we register:
# x-scheme-handler/mpv; x-scheme-handler/mpvs
#
# Look at the pairs below:
# http://domain:port/uri -> mpv://domain:port/uri
# https://domain:port/uri -> mpvs://domain:port/uri
#
# We expect to get an URL starting with mpv, and remove
# the first 3 chars, and put http before.
#
# 1) sudo cp mpv-handler.sh /usr/local/bin/mpv-handler.sh
# 2) cp mpv-handler.desktop ~/.local/share/applications/
# 2) update-desktop-database ~/.local/share/applications
#

if [[ "x$(echo "$1" | cut -c 1-3)" != "xmpv" ]]; then
    logger -t uri-mpv-handler "Only URL-s starting with mpv:// or mpvs:// allowed. Tried to open $1"
    exit
fi
url=http$(echo "$1" | cut -c 4-)
export LD_LIBRARY_PATH=/lib/x86_64-linux-gnu
logger -t uri-mpv-handler "Opening ${url}"
mpv_output=$(mpv ${url} 2>&1)
mpv_ret=$?
if [[ ${mpv_ret} -ne 0 ]]; then
    echo -e "${mpv_output}" | logger -t uri-mpv-handler
fi
