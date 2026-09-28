# SnowFallAnimation
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![ProcessWire 3](https://img.shields.io/badge/ProcessWire-3.x-orange.svg)](https://github.com/processwire/processwire)

A simple module for ProcessWire to create and animate snowflakes on a web page to add some winter magic to your
project.

![Snowfall demo](https://github.com/juergenweb/SnowfallAnimation/blob/main/images/snowfall-demo.png?raw=true)

This module is based on the nice JavaScript library by [ddosnotification](https://github.com/ddosnotification), which can be found [here](https://github.com/ddosnotification/snow-theme).

## Highlights
* Lightweight - About 2 KB minified
* Fully Responsive - Works perfectly on all devices and screen sizes
* Zero Dependencies - Pure JavaScript, no external libraries required
* Highly Customizable - Easy to adjust snowfall density, speed, size, and more
* Performance Optimized - Automatic cleanup and limited concurrent snowflakes
* Safe to Use - Non-intrusive design, won't interfere with page interactions
* Accessible - Respects the "reduce motion" setting of the operating system (no snowflakes for people who turned off animations)
* Cross-Browser Compatible - Works on all modern browsers
* Support for RockLanguage
* German translation included

## Configuration settings
1. Set the number of snowflakes on the page
2. Set a minimum and maximum size for the snowflakes
3. Set the color for the snowflakes
4. Set a minimum and maximum fall duration for the snowflakes
5. Change the text (snowflake icons) if needed
6. Enable/disable snowfall by setting a start and end date
7. Enable annual recurrence to start and end the snowfall on the same dates each year (no need to take care of it any longer).

## Requirements
* PHP>=8.0.0
* ProcessWire>=3.0.181
* LazyCron

## Installation

### Manual installation

Download the module folder and extract it under site/modules.

Make sure that the extracted folder is named SnowFallAnimation and not SnowFallAnimation-main or some other name.

Refresh the modules and install the module via the backend.

### Installation via ProcessWire backend (recommended)

Install the module as usual from the modules directory via the PW backend.

## Usage

Go to the module configuration page of this module in the backend and start the snowfall manually or set a start and end
date to activate or deactivate the snowfall on a timed basis.

If you are not satisfied with the default settings, you can make your changes inside the *Styling and settings for the snowflakes*
configuration section.

## Annual recurrence for activation/deactivation

This module uses LazyCron, which runs once a day, to check if the end date for the snowfall has already passed.

If so, LazyCron moves the start and end date forward by one year (or by several years, if the end date lies further
in the past) and stores the new dates in the database.

Please note: this only happens if annual recurrence is enabled.

## Changing the displayed date format

By default, this module uses the date format "Y-m-d" for the two date pickers, as well as for the status text at the top.

If you want to use a different date format, such as "d.m.Y", you can change the format within the language file for this
module because the format is saved as a translatable string.

## License

MIT License - feel free to use in both personal and commercial projects.
