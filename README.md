# DockerDiskUsage

**A simple tool to find out how much disk space your Docker "things" are using.**

This is not intended to do everything for everyone, just make it easy for us to get an informative snapshot of what's being used for what.

It's very much a work in progress, but hopefully will be useful to a few people.

## Requirements

* Docker 17+ (tested with 17.06.0-ce)
* PHP 7.1+

## Installation

You just need to clone this repo onto the machine and composer install.

## Usage

It feels wrong, but you need to run it as root so it can get the disk usage in places like `/var/lib/docker/volumes/sid_mysql/_data`.

For full options see...

    sudo ./bin/docker-disk-usage --help

For summary output...

    sudo ./bin/docker-disk-usage
    
This will give you something like this...
     
     SOMEAPPLICATION-----------------------------------------------------5.15GB
    
     Containers:       4.95GB
     Volumes:          0.20GB
    
    SOMEOTHERAPPLICATION-------------------------------------------------8.08GB
    
     Containers:       4.04GB
     Volumes:          0.20GB
    
    IMAGES (SHARED)-----------------------------------------------------11.06GB
    
    COMBINED TOTAL------------------------------------------------------19.14GB


For detailed output broken down by containers, volumes and images...

    sudo ./bin/docker-disk-usage --detailed
    
This will give you something like this...
    
    SOMEAPPLICATION------------------------------------------------------5.15GB
    
     Containers...
       chrome_1                                           created        1.25GB
       hub_1                                              running        1.27GB
       nginx_1                                            running        0.01GB
       sid_1                                              running        0.40GB
       adt_1                                              running        0.41GB
       node_1                                             exited         0.64GB
       wkhtmltopdf_1                                      running        0.59GB
       mysql_1                                            running        0.38GB
    
     Volumes...
       mysql                                                             0.20GB
    
    
    SOMEOTHERAPPLICATION-------------------------------------------------4.24GB
    
     Containers...
       chrome_1                                           exited         1.27GB
       hub_1                                              exited         1.27GB
       cms_1                                              exited         0.48GB
       mysql_1                                            exited         0.38GB
       node_1                                             exited         0.64GB
    
     Volumes...
       mysql                                                             0.20GB
    
    
    IMAGES (SHARED)-----------------------------------------------------11.06GB
    
       bbpdev/silverstripe:php7.1-xdebug                                 0.48GB
       bbpdev/silverstripe:latest,bbpdev/silverstripe:php7.1             0.48GB
       elgalu/selenium:latest                                            1.25GB
       bbpdev/symfony:7.1-xdebug                                         0.40GB
       mysql:5.7                                                         0.38GB
       bbpdev/symfony:latest                                             0.40GB
       bbpdev/php-wkhtmltopdf:latest                                     0.59GB
       bbpdev/npm-sass:latest                                            0.64GB
       bbpdev/npm-sass:6                                                 0.64GB
    
    
    COMBINED TOTAL-------------------------------------------------------4.24GB

