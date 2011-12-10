AjaXplorer-Azure
================

AjaXplorer-Azure is a [AjaXplorer](http://www.ajaxplorer.info/) plugin that allows browsing of Azure
Blob Storage. It runs on both Windows and Linux.

Dependencies
============
 - [Windows Azure SDK for PHP](http://phpazure.codeplex.com/) (bundled)
 - AjaXplorer needs to be installed
 
Installation
============
1. Copy files to ajaxplorer/plugins/access.azure
2. Edit ajaxplorer/server/conf/conf.php and add something like:

```php
$REPOSITORIES[2] = array(
	"DISPLAY"		=>	"Azure", 
	"AJXP_SLUG"		=>  "azure",
	"DRIVER"		=>	"azure", 
	"DRIVER_OPTIONS"=> array(
	),
);
```

License
=======
Copyright 2011-2012 Daniel Lo Nigro

This program is published under the LGPL GNU Lesser General Public License.
You should have received a copy of the license along with AjaXplorer.

The main conditions are as follows:

You must conspicuously and appropriately publish on each copy distributed 
an appropriate copyright notice and disclaimer of warranty and keep intact 
all the notices that refer to this License and to the absence of any warranty; 
and give any other recipients of the Program a copy of the GNU Lesser General 
Public License along with the Program. 

If you modify your copy or copies of the library or any portion of it, you may 
distribute the resulting library provided you do so under the GNU Lesser 
General Public License. However, programs that link to the library may be 
licensed under terms of your choice, so long as the library itself can be changed. 
Any translation of the GNU Lesser General Public License must be accompanied by the 
GNU Lesser General Public License.

If you copy or distribute the program, you must accompany it with the complete 
corresponding machine-readable source code or with a written offer, valid for at 
least three years, to furnish the complete corresponding machine-readable source code. 

Any of the above conditions can be waived if you get permission from the copyright holder.
AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
