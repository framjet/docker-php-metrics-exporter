FROM alpine:3.21

LABEL org.opencontainers.image.title="PHP Metrics Exporter (Lighttpd)"
LABEL org.opencontainers.image.authors="Aurimas Niekis <aurimas@niekis.lt>"
LABEL org.opencontainers.image.source="https://github.com/framjet/docker-php-metrics-exporter"
LABEL org.opencontainers.image.licenses="MIT"

ARG LIGHTTPD_VERSION=1.4.79-r0

RUN set -x \
    && apk add --no-cache \
    lighttpd${LIGHTTPD_VERSION:+=}${LIGHTTPD_VERSION} \
    && rm -rvf /var/cache/apk/* \
    && rm -rvf /etc/lighttpd/* /etc/logrotate.d/lighttpd /var/log/lighttpd /var/www/localhost \
    && rm -rvf /usr/lib/lighttpd/mod_accesslog.so \
    && rm -rvf /usr/lib/lighttpd/mod_ajp13.so \
    && rm -rvf /usr/lib/lighttpd/mod_authn_dbi.so \
    && rm -rvf /usr/lib/lighttpd/mod_authn_file.so \
    && rm -rvf /usr/lib/lighttpd/mod_authn_ldap.so \
    && rm -rvf /usr/lib/lighttpd/mod_cgi.so \
    && rm -rvf /usr/lib/lighttpd/mod_deflate.so \
    && rm -rvf /usr/lib/lighttpd/mod_dirlisting.so \
    && rm -rvf /usr/lib/lighttpd/mod_extforward.so \
    && rm -rvf /usr/lib/lighttpd/mod_h2.so \
    && rm -rvf /usr/lib/lighttpd/mod_magnet.so \
    && rm -rvf /usr/lib/lighttpd/mod_openssl.so \
    && rm -rvf /usr/lib/lighttpd/mod_proxy.so \
    && rm -rvf /usr/lib/lighttpd/mod_rrdtool.so \
    && rm -rvf /usr/lib/lighttpd/mod_sockproxy.so \
    && rm -rvf /usr/lib/lighttpd/mod_ssi.so \
    && rm -rvf /usr/lib/lighttpd/mod_userdir.so \
    && rm -rvf /usr/lib/lighttpd/mod_vhostdb.so \
    && rm -rvf /usr/lib/lighttpd/mod_vhostdb_dbi.so \
    && rm -rvf /usr/lib/lighttpd/mod_vhostdb_ldap.so \
    && rm -rvf /usr/lib/lighttpd/mod_wstunnel.so \
    && rm -rvf /usr/sbin/lighttpd-angel \
    && rm -rvf /usr/lib/liblua-5.4.so.0 \
    && rm -rvf /usr/lib/liblua-5.4.so.0.0.0 \
    && rm -rvf /usr/lib/lua5.4/liblua-5.4.so.0 \
    && rm -rvf /usr/lib/lua5.4/liblua-5.4.so.0.0.0 \
    && rm -rvf /usr/lib/libzstd.so.1 \
    && rm -rvf /usr/lib/libzstd.so.1.5.6 \
    && rm -rvf /usr/lib/libz.so.1 \
    && rm -rvf /usr/lib/libz.so.1.3.1 \
    && rm -rvf /etc/openldap/ldap.conf \
    && rm -rvf /usr/lib/liblber.so.2 \
    && rm -rvf /usr/lib/liblber.so.2.0.200 \
    && rm -rvf /usr/lib/libldap.so.2 \
    && rm -rvf /usr/lib/libldap.so.2.0.200 \
    && rm -rvf /usr/lib/libdbi.la \
    && rm -rvf /usr/lib/libdbi.so.1 \
    && rm -rvf /usr/lib/libdbi.so.1.1.0


ENV PHP_FPM_SERVER=php-fpm
ENV PHP_FPM_PORT=9000
ENV PHP_FPM_DOCUMENT_ROOT="/shared"
ENV PHP_FPM_SCRIPT_FILENAME="/shared/metrics.php"
ENV PHP_FPM_SCRIPT_NAME="/metrics.php"

COPY ./docker-entrypoint.sh /docker-entrypoint.sh
COPY ./docker-entrypoint.d /docker-entrypoint.d
COPY ./lighttpd/ /etc/lighttpd/

EXPOSE 80/tcp

ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["/usr/sbin/lighttpd", "-D", "-f", "/etc/lighttpd/lighttpd.conf"]
