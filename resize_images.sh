#!/bin/bash

# Directorio donde se encuentran las imágenes
IMAGES_DIR="$1"

# Tamaño máximo (en px)
MAX_SIZE=400

# Recorre los archivos en el directorio
for file in "$IMAGES_DIR"/*; do
  # Verifica si el archivo es una imagen
  if [ -f "$file" ] && [[ "$(file --mime-type -b "$file")" =~ ^image ]]; then
    # Utiliza mogrify para redimensionar la imagen
    mogrify -resize "${MAX_SIZE}x${MAX_SIZE}>" -quality 80 "$file"
  fi
done