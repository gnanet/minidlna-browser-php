# miniDLNA Database Browser - PHP app

A basic PHP app with bootstrap template to browse the contents of a miniDLNA database

## Prerequisites

You will need at least PHP 8.x, php-cli with sqlite3 support, and composer on the machine where your miniDLNA runs

## Installation


1. Clone this repository to the machine where your miniDLNA runs
   ```sh
   git clone https://github.com/gnanet/minidlna-browser-php.git
   ```
   The folder `minidlna-browser-php` is created, we will refer to this folder's full path as `PROJECT_BASEDIR`


2. Use the sample config file `example.env` save it as `.env`, and fill in the variables.
    ```
    DATABASE='path-to-the-location-of/files.db'
    BASE_URL ='http://IP-ADDRESS-TO-MINIDLNA:8200/MediaItems/'
    ROOT_ID='64'
    PFW_URL=''
    PFW_CMD=''
    MPV_PLAY_URI=0
    AUTH_ALLOW=''
    AUTH_PASS=''
    ```

    **Optional:** You can set a username and password to protect the minidlna content from unauthorized browsing. **Authentication is enabled only if both values are set**
    ```
    DATABASE='path-to-the-location-of/files.db'
    BASE_URL ='http://IP-ADDRESS-TO-MINIDLNA:8200/MediaItems/'
    ROOT_ID='64'
    PFW_URL=''
    PFW_CMD=''
    MPV_PLAY_URI=0
    AUTH_ALLOW='browser-user'
    AUTH_PASS='hard2guess-password'
    ```

    **Optional:** If `PFW_CMD` and `PFW_URL` is set, additional info and clipboard link is shown below video-items.

    ```
    DATABASE='path-to-the-location-of/files.db'
    BASE_URL ='http://IP-ADDRESS-TO-MINIDLNA:8200/MediaItems/'
    ROOT_ID='64'
    PFW_URL='http://127.0.0.1:8201/MediaItems/'
    PFW_CMD='ssh -L L8201:IP-ADDRESS-TO-MINIDLNA:8200 your-ssh-user@your-gateway-host-to-minidlna'
    MPV_PLAY_URI=0
    AUTH_ALLOW=''
    AUTH_PASS=''
    ```

    **Optional:** with `MPV_PLAY_URI` set to `1`, you can enable a custom-uri scheme, and display the respective urls, which allows calling mpv for playback directly from the browser
    **NOTE:** This works on moderately recent versions of Linux, you will need to install `mpv`, and add `scripts/mpv-handler.desktop` to your `~/.local/share/applications`, and place `scripts/mpv-handler.sh` into `/usr/local/bin/`

    ```
    DATABASE='path-to-the-location-of/files.db'
    BASE_URL ='http://IP-ADDRESS-TO-MINIDLNA:8200/MediaItems/'
    ROOT_ID='64'
    PFW_URL=''
    PFW_CMD=''
    MPV_PLAY_URI=1
    AUTH_ALLOW=''
    AUTH_PASS=''
    ```

3. Use composer to install the required dependencies:
    ```
    $ composer install
    ```

## Usage

> NOTE: You may enable the IP of your miniDLNA server as secure origin, providing `http://IP-ADDRESS-TO-MINIDLNA:8290/` in the input-box, and set the flag as enabled
> 
> [chrome://flags/#unsafely-treat-insecure-origin-as-secure](chrome://flags/#unsafely-treat-insecure-origin-as-secure)
> 
> If copy-to-clipboard works, then you dont need to create a certificate for https, possible a self-signed, having to add the signer-cert as trusted CA.

0. Set-up the SSL wrapper
  0.1 Copy `PROJECT_BASEDIR/stunnel.conf.example` to `PROJECT_BASEDIR/stunnel.conf`

    Fill applicable values:
    - usually on a raspberry-pi the `UNPRIVILEGED_USER` + `UNPRIVILEGED_GROUP` will be `pi`
    - the `IP-ADDRESS-TO-MINIDLNA` is the IP address of your miniDLNA server
    - the `HOSTNAME-OF-CERT` should be a hostname, that your browser is able to resolve to `IP-ADDRESS-TO-MINIDLNA`, the certificate may be a self-signed cert, in that case you will need to add the signer-cert as trusted CA in your browser

    ```
    setuid = UNPRIVILEGED_USER
    setgid = UNPRIVILEGED_GROUP
    debug = notice
    pid = PROJECT_BASEDIR/stunnel.pid
    output = PROJECT_BASEDIR/stunnel.log

    [https]
    accept  = IP-ADDRESS-TO-MINIDLNA:8291
    connect = IP-ADDRESS-TO-MINIDLNA:8290
    cert = PROJECT_BASEDIR/HOSTNAME-OF-CERT.pem
    TIMEOUTclose = 0
    debug = crit
    ```

  0.2 Start the stunnel

    ```
    stunnel4 PROJECT_BASEDIR/stunnel.conf
    ```

1. Start the app with the built-in server of PHP (usually this app should only be used by you) :
    ```
    $ cd PROJECT_BASEDIR && php -S 0.0.0.0:8290 browser.php 2>&1 | grep -vE " Accepted| Closing"
    ```
2. Navigate to the URL, replace `IP-ADDRESS-TO-MINIDLNA` with the IP address of your miniDLNA server, or replace `HOSTNAME-OF-CERT` with your choosen hostname
    ```
    http://IP-ADDRESS-TO-MINIDLNA:8290/ or https://HOSTNAME-OF-CERT:8291/
    ```
3. Navigate trough the folders structure, to see the content list of the miniDLNA server.

4. For video and audio media types there are clipboard-icons that you can click to copy either the LAN URL, or if it was set, the URL for Portforwarded connections, which you can use to open them in your mediaplayer of choice.

## To enable copy-paste in desktop browsers in the same LAN, you need

1. Set in `/etc/hosts` `IP-ADDRESS-TO-MINIDLNA HOSTNAME-OF-CERT`

2. Add the PEM encoded signer certificate (crt file), as trusted CA to your browser

3. Visit https://HOSTNAME-OF-CERT:8291/


<!-- ACKNOWLEDGMENTS -->
## Acknowledgments

### Libraries

* [symfony/dotenv](https://github.com/symfony/dotenv)
* [steampixel/simplePHPRouter](https://github.com/steampixel/simplePHPRouter)
* [twigphp/Twig](https://github.com/twigphp/Twig)

### Bootstrap template

* [Bootdey - check file manager](https://www.bootdey.com/snippets/view/check-file-manager)


### Initial ideas

* [danielroseman/minidlna-browser](https://github.com/danielroseman/minidlna-browser) the python / Flask appication
* [gnanet/minidlna-browser](https://github.com/gnanet/minidlna-browser) customized fork by me

