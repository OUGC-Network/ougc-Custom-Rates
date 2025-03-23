<h3 align="center">ougc Custom Rates</h3>

<div align="center">

[![Status](https://img.shields.io/badge/status-active-success.svg)]()
[![GitHub Issues](https://img.shields.io/github/issues/OUGC-Network/ougc-Custom-Rates.svg)](./issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/OUGC-Network/ougc-Custom-Rates.svg)](./pulls)
[![License](https://img.shields.io/badge/license-GPL-blue)](/LICENSE)

</div>

---

<p align="center"> Create custom rates for users to use in posts.
    <br> 
</p>

## 📜 Table of Contents <a name = "table_of_contents"></a>

- [About](#about)
- [Getting Started](#getting_started)
    - [Dependencies](#dependencies)
    - [File Structure](#file_structure)
    - [Install](#install)
    - [Update](#update)
    - [Template Modifications](#template_modifications)
- [Settings](#settings)
    - [File Level Settings](#file_level_settings)
- [Templates](#templates)
- [Built Using](#built_using)
- [Authors](#authors)
- [Acknowledgments](#acknowledgement)
- [Support & Feedback](#support)

## 🚀 About <a name = "about"></a>

This plugin lets you create unlimited rating types like "Like" or "Dislike," while showing detailed rating stats in user
profiles. You can add reputation points when users rate posts, set group permissions, and even configure ratings per
forum. Features like requiring ratings to download attachments or hiding posts based on low ratings help you control and
shape user interactions.

[Go up to Table of Contents](#table_of_contents)

## 📍 Getting Started <a name = "getting_started"></a>

The following information will assist you into getting a copy of this plugin up and running on your forum.

### Dependencies <a name = "dependencies"></a>

A setup that meets the following requirements is necessary to use this plugin.

- [MyBB](https://mybb.com/) >= 1.8
- PHP >= 7
- [MyBB-PluginLibrary](https://github.com/frostschutz/MyBB-PluginLibrary) >= 13

### File structure <a name = "file_structure"></a>

  ```
   .
   ├── admin
   │ ├── modules
   │ │ ├── config
   │ │ │ ├── ougc_customrep.php
   ├── images
   │ ├── ougc_customrep
   │ │ ├── default.png
   ├── inc
   │ ├── languages
   │ │ ├── english
   │ │ │ ├── admin
   │ │ │ │ ├── config_ougc_customrep.lang.php
   │ │ │ ├── ougc_customrep.lang.php
   │ │ ├── espanol
   │ │ │ ├── admin
   │ │ │ │ ├── config_ougc_customrep.lang.php
   │ │ │ ├── ougc_customrep.lang.php
   │ ├── plugins
   │ │ ├── ougc
   │ │ │ ├── CustomReputation
   │ │ │ │ ├── admin
   │ │ │ │ │ ├── user.php
   │ │ │ │ ├── hooks
   │ │ │ │ │ ├── admin.php
   │ │ │ │ │ ├── forum.php
   │ │ │ │ │ ├── shared.php
   │ │ │ │ ├── templates
   │ │ │ │ │ ├── .html
   │ │ │ │ │ ├── headerinclude.html
   │ │ │ │ │ ├── headerinclude_fa.html
   │ │ │ │ │ ├── headerinclude_xthreads.html
   │ │ │ │ │ ├── headerinclude_xthreads_editpost.html
   │ │ │ │ │ ├── headerinclude_xthreads_editpost_hidecode.html
   │ │ │ │ │ ├── misc.html
   │ │ │ │ │ ├── misc_error.html
   │ │ │ │ │ ├── misc_multipage.html
   │ │ │ │ │ ├── misc_row.html
   │ │ │ │ │ ├── modal.html
   │ │ │ │ │ ├── postbit_reputation.html
   │ │ │ │ │ ├── profile.html
   │ │ │ │ │ ├── profile_empty.html
   │ │ │ │ │ ├── profile_number.html
   │ │ │ │ │ ├── profile_row.html
   │ │ │ │ │ ├── rep.html
   │ │ │ │ │ ├── rep_img.html
   │ │ │ │ │ ├── rep_img_fa.html
   │ │ │ │ │ ├── rep_number.html
   │ │ │ │ │ ├── rep_voted.html
   │ │ │ │ │ ├── xthreads_js.html
   │ │ │ │ ├── admin.php
   │ │ │ │ ├── class_alerts.php
   │ │ │ │ ├── core.php
   │ │ │ │ ├── settings.json
   │ │ │ │ ├── stylesheet.css
   │ │ │ ├── ougc_customrep.php
   ├── jscripts
   │ ├── ougc_customrep.js
   ```

### Installing <a name = "install"></a>

Follow the next steps in order to install a copy of this plugin on your forum.

1. Download the latest package from the [MyBB Extend](https://community.mybb.com/mods.php) site or
   from the [repository releases](https://github.com/OUGC-Network/ougc-Custom-Rates/releases/latest).
2. Upload the contents of the _Upload_ folder to your MyBB root directory.
3. Browse to _Configuration » Plugins_ and install this plugin by clicking _Install & Activate_.
4. Browse to _Settings_ to manage the plugin settings.

### Updating <a name = "update"></a>

Follow the next steps in order to update your copy of this plugin.

1. Browse to _Configuration » Plugins_ and deactivate this plugin by clicking _Deactivate_.
2. Follow step 1 and 2 from the [Install](#install) section.
3. Browse to _Configuration » Plugins_ and activate this plugin by clicking _Activate_.
4. Browse to _Settings_ to manage the plugin settings.

### Template Modifications <a name = "template_modifications"></a>

To display Rates data it is required that you edit the following template for each of your themes.

1. Place `{$post['customrep']}` after `{$post['button_rep']}` in the `postbit` and `postbit_classic` templates to
   display the rates section in posts.
2. Place `{$post['customrep_post_visibility']}` after `{$post_visibility}` in the `postbit` and `postbit_classic`
   templates for the _Hide Post On Count_ feature.
3. Place `{$post['customrep_ignorebit']}` after `{$deleted_bit}` in the `postbit` and `postbit_classic` templates for
   the _Hide Post On Count_ feature.
4. Replace `{$post['userreputation']}` with `<span id="customrep_rep_{$post['pid']}">{$post['userreputation']}</span>`
   in the `postbit_reputation` template.
5. Place `{$thread['customrep']}` after `{$attachment_count}` in the `postbit` and `forumdisplay_thread` templates to
   display the rates section in the forum display thread list.
6. Place `{$announcement['customrep']}` after `{$senditem}` in the `portal_announcement` template to display the rates
   section in the portal announcements.
7. Place `{$memprofile['customrep']}` after `{$signature}` in the `member_profile` template to display the rates stats
   in profiles.

[Go up to Table of Contents](#table_of_contents)

## 🛠 Settings <a name = "settings"></a>

Below you can find a description of the plugin settings.

### Main Settings

- **First Post Only Global Switch** `yesNo`
    - _Whether if enable this feature only for the first post of a thread. Turn off to manage on a per rate basis._
- **Allow Deletion Global Switch** `yesNo`
    - _Allow deletion of ratings. Turn on to manage on a per rate basis._
- **Multipage Per Page** `numeric`
    - _Maximum number of options to show per page._
- **Use Font Awesome Icons** `yesNo`
    - _Activate this setting if you want to use font awesome icons instead of images._
- **Font Awesome ACP Code** `text`
    - _Insert the ACP code to load if using Font Awesome icons._
- **Display On Thread Listing** `select`
    - _Select the forums where you want to display ratings within the forum thread list._
- **Display On Portal Announcements** `select`
    - _Select the forums where threads need to be from to display its custom rates box within the portal announcements
      listing._
- **Active xThreads Hide Feature** `select`
    - _Select which xThreads fields this feature should hijack to control display status._
- **Display Users Stats in Profiles** `yesNo`
    - _Enable this setting to display user stats within profiles._
- **Enable Ajax Features** `yesNo`
    - _Enable Ajax features. Please note that the "Enable XMLHttp request features?" setting under the "Server and
      Optimization Options" settings group needs to be turned on ("Yes") for Ajax features to work._
- **Allow Guests to View Popup** `yesNo`
    - _Enable this setting if you want to allow guests viewing rate detail modals._
- **Multiple Rating Global Switch** `yesNo`
    - _Enable this setting to allow users to rate post multiple times (using different ratings)._

### File Level Settings <a name = "file_level_settings"></a>

Additionally, you can force your settings by updating the `SETTINGS` array constant in the `ougc\CustomRates\Core`
namespace in the `./inc/plugins/ougc_customrep.php` file. Any setting set this way will always bypass any front-end
configuration. Use the setting key as shown below:

```PHP
define('ougc\CustomRates\Core\SETTINGS', [
    'allowImports' => false,
    'myAlertsVersion' => '2.1.0'
]);
```

[Go up to Table of Contents](#table_of_contents)

## 📐 Templates <a name = "templates"></a>

The following is a list of templates available for this plugin.

- `ougccustomrep`
    - _front end_;
- `ougccustomrep_headerinclude`
    - _front end_;
- `ougccustomrep_headerinclude_fa`
    - _front end_;
- `ougccustomrep_headerinclude_xthreads`
    - _front end_;
- `ougccustomrep_headerinclude_xthreads_editpost`
    - _front end_;
- `ougccustomrep_headerinclude_xthreads_editpost_hidecode`
    - _front end_;
- `ougccustomrep_misc`
    - _front end_;
- `ougccustomrep_misc_error`
    - _front end_;
- `ougccustomrep_misc_multipage`
    - _front end_;
- `ougccustomrep_misc_row`
    - _front end_;
- `ougccustomrep_modal`
    - _front end_;
- `ougccustomrep_postbit_reputation`
    - _front end_;
- `ougccustomrep_profile`
    - _front end_;
- `ougccustomrep_profile_empty`
    - _front end_;
- `ougccustomrep_profile_number`
    - _front end_;
- `ougccustomrep_profile_row`
    - _front end_;
- `ougccustomrep_rep`
    - _front end_;
- `ougccustomrep_rep_img`
    - _front end_;
- `ougccustomrep_rep_img_fa`
    - _front end_;
- `ougccustomrep_rep_number`
    - _front end_;
- `ougccustomrep_rep_voted`
    - _front end_;
- `ougccustomrep_xthreads_js`
    - _front end_;

[Go up to Table of Contents](#table_of_contents)

## 📐 Usage <a name = "usage"></a>

-Output in Custom Variable

-xthreads support

[Go up to Table of Contents](#table_of_contents)

## ⛏ Built Using <a name = "built_using"></a>

- [MyBB](https://mybb.com/) - Web Framework
- [MyBB PluginLibrary](https://github.com/frostschutz/MyBB-PluginLibrary) - A collection of useful functions for MyBB
- [PHP](https://www.php.net/) - Server Environment

[Go up to Table of Contents](#table_of_contents)

## ✍️ Authors <a name = "authors"></a>

- [@Omar G](https://github.com/Sama34) - Idea & Initial work

See also the list of [contributors](https://github.com/OUGC-Network/ougc-Custom-Rates/contributors) who participated in
this
project.

[Go up to Table of Contents](#table_of_contents)

## 🎉 Acknowledgements <a name = "acknowledgement"></a>

- [The Documentation Compendium](https://github.com/kylelobo/The-Documentation-Compendium)

[Go up to Table of Contents](#table_of_contents)

## 🎈 Support & Feedback <a name="support"></a>

This is free development and any contribution is welcome. Get support or leave feedback at the
official [MyBB Community](https://community.mybb.com/thread-159249.html).

Thanks for downloading and using our plugins!

[Go up to Table of Contents](#table_of_contents)