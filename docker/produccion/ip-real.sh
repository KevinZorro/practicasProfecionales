#!/bin/sh
# IP real del visitante detrás del proxy de la universidad (ADR 0006, B3.8).
#
# El proxy termina el HTTPS y reenvía la petición con la IP del visitante en
# X-Forwarded-For. Sin esto, nginx cuenta a todos como uno solo —el proxy— y
# sus topes por IP pasan a ser de toda la universidad. La cabecera solo se
# cree si la petición viene de una IP de PROXIES_DE_CONFIANZA, la misma
# variable con la que Laravel confía en el proxy: de cualquier otro, sería
# una IP inventada.
#
# Lo corre la imagen de nginx al arrancar (/docker-entrypoint.d). Un valor
# que no es una IP ni un rango no arranca nginx: confiar en algo mal escrito
# es peor que no confiar.
# -f: sin comodines, para que un «*» no se convierta en los archivos de la carpeta.
set -euf

destino="${IP_REAL_DESTINO:-/etc/nginx/conf.d/ip-real.conf}"
: > "$destino"

proxies="$(printf '%s' "${PROXIES_DE_CONFIANZA:-}" | tr ',' ' ')"
[ -n "$(printf '%s' "$proxies" | tr -d ' ')" ] || exit 0

for proxy in $proxies; do
    case "$proxy" in
        '*')
            # Confía en quien llame: solo si nadie más llega a este nginx.
            printf 'set_real_ip_from 0.0.0.0/0;\nset_real_ip_from ::/0;\n' >> "$destino"
            continue
            ;;
    esac

    if ! printf '%s' "$proxy" | grep -Eq '^[0-9A-Fa-f.:]+(/[0-9]{1,3})?$'; then
        echo "ip-real: «$proxy» no es una IP ni un rango. Revisa PROXIES_DE_CONFIANZA en el .env." >&2
        exit 1
    fi

    printf 'set_real_ip_from %s;\n' "$proxy" >> "$destino"
done

printf 'real_ip_header X-Forwarded-For;\nreal_ip_recursive on;\n' >> "$destino"
