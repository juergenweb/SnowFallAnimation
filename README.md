# SnowfallAnimation
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![ProcessWire 3](https://img.shields.io/badge/ProcessWire-3.x-orange.svg)](https://github.com/processwire/processwire)

A simple module for ProcessWire to create and animate snowflakes on a web page with an HTML5 canvas to add some winter
magic to your project.

![Snowfall demo](https://github.com/juergenweb/SnowfallAnimation/blob/main/images/snowfall-demo.png?raw=true)

This module is based on the nice JavaScript library of [ddosnotification](https://github.com/ddosnotification) and can be found [here](https://github.com/ddosnotification/snow-theme).

## Highlights
* Lightweight - Under 1kb minified
* Fully Responsive - Works perfectly on all devices and screen sizes
* Zero Dependencies - Pure JavaScript, no external libraries required
* Highly Customizable - Easy to adjust snowfall density, speed, size, and more
* Performance Optimized - Automatic cleanup and limited concurrent snowflakes
* Safe to Use - Non-intrusive design, won't interfere with page interactions
* Cross-Browser Compatible - Works on all modern browsers
* Support for RockLanguage
* German Translations included

## Configuration settings
1. Set the number of snowflakes on the page
2. Set a minimum and maximum size for the snowflakes
3. Set the color for the snowflakes 
4. Set a minimum and maximum speed for the falling snowflakes
5. Change the text (snowflakes icon) for the canvas if needed
6. Enable/disable snowfall by setting a start and end date
7. Enable annual recurrences to start and end snowfall on the same date each year (no need to take care any longer).

## Requirements
* PHP>=8.0.0
* ProcessWire>=3.0.181
* LazyCron

## Installation

### Manuell installation

Download the module folder and extract it under site/modules.

Take care that the extracted folders name is SnowFallAnimation and not SnowFallAnimation-main or another name.

Refresh the modules and install the module via the Backend.

### Installation via ProcessWire backend (recommended)

Install the module as usual from the modules directory via the PW backend.

## Usage

Go to the module configuration page of this module in the backend and start the snowfall manually or set a start and end
date to activate or deactivate the snowfall on a timed basis.

If you are not satisfied with the default settings, you can make your changes inside the *Styles and settings* configuration
section.

## Annual recurrence for activation/deactivation

This module uses LazyCron, which runs once a day, to check if the end date for snowfall has already passed.

If so, LazyCron stores the new date (= old date plus 1 year) in the database.

Just to mention: This only happens if annual recurrence is enabled.

## Changing the date format displayed

By default, this module uses the date format "Y-m-d" for the two date pickers, as well as the status text at the top.

If you want to use a different date format, such as "d.m.Y", you can change the format within the language file for this
module because the format is saved as a translatable string.

## License

MIT License - feel free to use in both personal and commercial projects.



