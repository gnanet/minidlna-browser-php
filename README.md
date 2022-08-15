# miniDLNA Database Browser - PHP app

A basic PHP app with bootstrap template to browse the contents of a miniDLNA database

## Prerequisites

You will need at least PHP 7.x, php-cli with sqlite3 support, and composer on the machine where your miniDLNA runs

## Installation


1. Clone this repository to the machine where your miniDLNA runs
   ```sh
   git clone https://github.com/gnanet/minidlna-browser-php.git
   ```
2. Use the sample config file `example.env` save it as `.env`, and fill in the variables.
    ```
    DATABASE='path-to-the-location-of/files.db'
    BASE_URL ='http://IP-ADDRESS-TO-MINIDLNA:8200/MediaItems/'
    ROOT_ID='64'
    PFW_URL=''
    PFW_CMD=''
    ```

    **Optional:** If `PFW_CMD` and `PFW_URL` is set, additional info and clipboard link is shown below video-items.

    ```
    DATABASE='path-to-the-location-of/files.db'
    BASE_URL ='http://IP-ADDRESS-TO-MINIDLNA:8200/MediaItems/'
    ROOT_ID='64'
    PFW_URL='http://127.0.0.1:8201/MediaItems/'
    PFW_CMD='ssh -L L8201:IP-ADDRESS-TO-MINIDLNA:8200 your-ssh-user@your-gateway-host-to-minidlna'
    ```
3. Use composer to install the required dependencies:
    ```
    $ composer install
    ```

## Usage

1. Start the app with the built-in server of PHP (usually this app should only be used by you) :
    ```
    $ php -S 0.0.0.0:8290 browser.php
    ```
2. Navigate to the URL, replace `IP-ADDRESS-TO-MINIDLNA` with the IP address of your miniDLNA server
    ```
    http://IP-ADDRESS-TO-MINIDLNA:8290/
    ```
3. Navigate trough the folders structure, to see the content list of the miniDLNA server.

4. For video and audio media types there are clipboard-icons that you can click to copy either the LAN URL, or if it was set, the URL for Portforwarded connections, which you can use to open them in your mediaplayer of choice.

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

