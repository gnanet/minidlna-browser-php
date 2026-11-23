# minidlna on a public server


## Definitions

- **@@fqdn-for-minidlna-dms@@** - a fully qualified domain name, that points to your public server, this hostname will be used for requesting the media files from minidlna directly
- **@@fqdn-for-minidlna-browser@@** - a fully qualified domain name, that points to your public server, this hostname will be used for the minidlna-browser-php application, this is the project you actually visiting here.
- **@@browser-user@@** - in public server setups the access to the browser interface must be protected, this should be the username
- **@@browseruser-password@@** - in public server setups the access to the browser interface must be protected, this should be a really hard to guess password for the username above


## Pre-requisite configurations and installs

### 1. Block SSDP (UPnP Discovery) Multicast, to prevent the single server setup leaking information

  <sup><sub>initial idea source: https://oneuptime.com/blog/post/2026-03-20-block-unnecessary-broadcast-firewall-rules/view#block-ssdp-upnp-discovery-multicast</sub></sup>

  A. Using iptables in rules.v4

  ```
    -A INPUT -i eno1 -p udp --dport 1900 -d 239.255.255.250 -j DROP -m comment --comment "block ssdp multicast in"
    -A OUTPUT -p udp --dport 1900 -d 239.255.255.250 -j DROP -m comment --comment "block ssdp multicast out"
  ```

  B. Using nftables

  ```
    nft add rule inet filter input [position 1] udp dport 1900 drop comment "block ssdp multicast in"
    nft add rule inet filter output [position 1] udp dport 1900 accept comment "block ssdp multicast out"
  ```

### 2. Install and configure minidlna with the public fqdn hostname

  ```
    apt install minidlna
  ```


   In `/etc/minidlna.conf` change following values to align to the public server role, and set also the generic settings.

```
    network_interface=lo
    port=8200
    presentation_url=http://@@fqdn-for-minidlna-dms@@/
```


### 3. Issue a certificate using acme.sh for @@fqdn-for-minidlna-browser@@ and install the certificate to nginx

```
    export APP_DOMAIN=@@fqdn-for-minidlna-browser@@

    acme.sh -k 4096 --issue -w /var/www/acme-challenge/ -d ${APP_DOMAIN}
    mkdir -p /etc/nginx/ssl/${APP_DOMAIN}; /root/.acme.sh/acme.sh --install-cert -d ${APP_DOMAIN} --cert-file /etc/nginx/ssl/${APP_DOMAIN}/cert.pem --key-file /etc/nginx/ssl/${APP_DOMAIN}/privkey.pem --fullchain-file /etc/nginx/ssl/${APP_DOMAIN}/fullchain.pem --reloadcmd "/usr/sbin/nginx -t > /dev/null 2>&1 && /usr/sbin/nginx -s reload || exit 1"
```

### 4. Create nginx configurations, one for proxying minidlna presentation port 8200, and a proxy for fqdn-for-minidlna-browser app, with a LetsEncrypt SSL cert

Create an nginx proxy for the DMS into sites-available/@@fqdn-for-minidlna-dms@@.conf

  <sup><sub>based on source: https://www.selfhostedninja.com/minidlna-a-beginner-friendly-guide-to-self-hosting/ </sub></sup>

```
server {
    listen 80;
    server_name @@fqdn-for-minidlna-dms@@;

    include /etc/nginx/snippets/acme.conf;
    include /etc/nginx/snippets/sec-vcs.conf;

    location / {
        proxy_pass http://127.0.0.1:8200;  # MiniDLNA default port
        proxy_set_header Host 127.0.0.1; # avoid dns rebinding errors
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

Create an nginx proxy for the minidlna-browser-php app into sites-available/@@fqdn-for-minidlna-browser@@.conf

```
server {
    listen 80;
    listen [::]:80;
    server_name @@fqdn-for-minidlna-browser@@;

    include /etc/nginx/snippets/acme.conf;
    include /etc/nginx/snippets/sec-vcs.conf;

    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name @@fqdn-for-minidlna-browser@@;

    ssl_certificate /etc/nginx/ssl/@@fqdn-for-minidlna-browser@@/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/@@fqdn-for-minidlna-browser@@/privkey.pem;
    include /etc/nginx/snippets/options-ssl-nginx.conf;
    ssl_dhparam /etc/nginx/ssl/dhparam.pem;

    include /etc/nginx/snippets/acme.conf;
    include /etc/nginx/snippets/sec-vcs.conf;

    location / {
        proxy_pass http://127.0.0.1:8290;  # MiniDLNA php browser app http port
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
    charset utf-8;
    client_max_body_size 50m;
    fastcgi_buffers 16 16k;
    fastcgi_buffer_size 32k;
    fastcgi_read_timeout 300;
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
}
```

### 5. Enable both @@fqdn-for-minidlna-dms@@.conf and @@fqdn-for-minidlna-browser@@.conf

  **NOTE: i use a python script called [ngx-conf](https://github.com/gnanet/ngx-conf)**

```
    ngx-conf -e @@fqdn-for-minidlna-dms@@.conf
    ngx-conf -e @@fqdn-for-minidlna-browser@@.conf
```

### 6. Test and load the new nginx configs

```
    nginx -t && nginx -s reload
```

### 7. Ensure php 8.4 and sqlite3 extension is installed

```
    apt install php8.4-cli php8.4-sqlite3
```

## Installation

1. Clone the repository to the server where your miniDLNA runs
   ```sh
   git clone https://github.com/gnanet/minidlna-browser-php.git
   ```
   The folder `minidlna-browser-php` is created, we will refer to this folder's full path as `PROJECT_BASEDIR`


2. create a .env file:

    ```
    DATABASE='/var/cache/minidlna/files.db'
    BASE_URL='http://@@fqdn-for-minidlna-dms@@/MediaItems/'
    MPV_PLAY_URI=1
    ROOT_ID='64'
    PFW_URL=''
    PFW_CMD=''
    AUTH_ALLOW='@@browser-user@@'
    AUTH_PASS='@@browseruser-password@@'
    ```

3. Use composer to install the required dependencies:
    ```
    $ composer install
    ```

4. In a screen session execute

    ```
    cd ${PROJECT_BASEDIR} && php8.4 -S 0.0.0.0:8290 browser.php 2>&1 | grep -vE " Accepted| Closing"
    ```

5. Navigate to the URL @@fqdn-for-minidlna-browser@@

    ```
    https://@@fqdn-for-minidlna-browser@@/
    ```

6. Navigate trough the folders structure, to see the content list of the miniDLNA server.

7. For video and audio media types there are clipboard-icons that you can click to copy either the LAN URL, or if it was set, the URL for Portforwarded connections, which you can use to open them in your mediaplayer of choice.

