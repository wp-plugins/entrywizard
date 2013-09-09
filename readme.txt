=== EntryWizard ===
Contributors: Josie Stauffer
Tags: upload, image, competition, spreadsheet, camera club, photography
Requires at least: 3.5
Tested up to: 3.5.2
Stable tag: 0.9.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Uploading by logged-in users of sets of image files and associated data. Administrators design the upload form, and download the images and data.

== Description ==

EntryWizard was developed to cater to the needs of camera club competitions.  In these competitions members may submit multiple images with titles and other data attached.

* Administrators use the software to design web forms, asking for information as text inputs and/or drop-down menu items as well as image files. 

* An example of the sort of rules the form is able to enforce might be something like this:

    +  Each image must have: a short title; a category which must be one of "Nature", "Photojournalism" or "Portrait"; a type which must be one of "Digital", "Slide" or "Print" 
    +  Up to 6 entries may be submitted, each with or without a digital version 
    +  At most 2 of the 6 images may be in the "Nature" category. 
    +  Any image of type "Digital" must have an uploaded digital version
    +  The images must be in jpeg format, with maximum dimensions of 1280 x 1024 pixels and maximum size of 1M.
     
* The administrators may set up any number of different "layouts" ( specifications of what information to ask for and what limits to enforce ), assigning an identifier to each.   
  
* The webforms thus designed may be incorporated into any page using a shortcode. Any number of different webforms may be created, and one of the layouts is assigned to each one. 

* Image upload items on the forms use HTML5 constructs where available to give the user immediate feedback if the image does not meet size or dimension requirements. ( Reportedly available in Firefox 4+, Chrome 7+, InternetExplorer 10+, Opera 12+, Safari 6+ ).  
  Administrators should use a recent browser to test the forms. Internet Explorer earlier than 9 will not fully support the administration interface.  

* Each webform is given a unique alphanumeric identifier, and this identifier is used within a shortcode to insert the webform in a page or post. Multiple webforms may appear on one page, although currently they have to be submitted individually. 

* Each webform separately may be set as "open for upload" or "closed".  If it is open, the shortcode is replaced by the upload form.  If it is closed, the shortcode is replaced by a message saying that the upload is closed.  The webform may also be opened temporarily to specified members only.

* When items have been uploaded, the administrator may either view the items online, or download them.  A spreadsheet (in comma-separated .csv format) showing the uploaded data together with some optional information about the users, may also be downloaded.

* The uploaded images may be attached to pre-existing pages for display in a gallery.

* There is a fine-grained permissions system allowing administrators to grant an individual user various levels of control over the webforms and layouts.
  Only logged-in users may see the webform.


== Installation ==

* Unzip the file into your plugins folder. In the administration area, go to Plugins, look for "EntryWizard", and click "activate".  

* The "EntryWizard" links should appear on the administration sidebar.

* NB: If you are using the "DB Cache Reloaded Fix" plugin, you need to add "EWZ_|ewz_" to the cache filter (where it says "Do not cache queries that contains this input contents").  If you don't do this, users will not get immediate feedback when they upload, but will have to wait for the cache to expire.  Some other caching plugins may also need to be configured in this way.

 
= Usage =

* The initial setup comes with two sample layouts and one sample webform.  The sample webform has been assigned the identifier "test".
  To show the sample webform on your page, simply include the shortcode

       [ewz_show_webform identifier="test"]

   in the page where you wish the form to appear.  
  
* Quickstart for the impatient:

  1. In the EntryWizard->Layouts admin page, click "Add a new layout" with options copied from the sample.
  
  2. Give it a new title and save it.

  3. In the EntryWizard->Webforms admin page, click "Add a new Webform".  In the new webform:
             + give it a name and an identifier
             + select your newly-created layout
             + click "Open for Uploads"
             + save the webform

  4. Create a new test page, containing 
            [ewz_show_webform identifier="xxx"]
     where xxx is the identifier you just created for your new webform.
  
  5. Now you can experiment, changing the settings for your own layout and webform and checking the effect by reloading your test page. If it turns out badly, delete that layout and start over with a fresh copy of the sample one.
     


* Most items in the EntryWizard admin pages have "help" icons beside them.  Clicking one of these should pop up a window with more detailed information. 

* The "layout" governs the appearance of the form in the page. To change the layout of a form, go to the "webforms" page, open the relevant webform and select a different layout.

* To change a layout, or create a new one, go to the "layouts" page. To save the work of filling out all the details every time, new layouts are first created as copies of existing ones, to be modified as required. It is suggested that you keep the sample layouts for reference until you are comfortable with the software, and then modify the first one to be your "base" layout.

* To open a new webform for upload, go to EntryWizard->Webforms in the Wordpress Admin menu, click on the webform and check "open for uploads".  To open a closed webform for selected individuals only, click "Show user selection list", and select the users.  Then click "Save Changes".

* Logged-in users may now upload images by navigating to the page containing the "ewz_show_webform" shortcode.

* Administrators may download images and/or spreadsheet by going to EntryWizard->Webforms in the Wordpress Admin menu, clicking on the webform and selecting the preferred download option.  Or clicking "Manage Items" takes you to a list of all the images, which you may view, delete, or attach to a page for display in a wordpress gallery. 

* EntryWizard saves the images in a special subfolder of the wordpress "uploads" folder.  The image filenames are derived from the user's original filenames in the following way:
    1. All characters except letters, digits, underscores, dashes,  and all periods except the last,  are replaced by '_'. This ensures the filename is acceptable on most systems.
    2. If the base filename then ends with a digit, another '_' is appended.  This makes extra digits added by Wordpress in the case of duplicate filenames more legible. It also ensures that no filename is totally numeric - Wordpress handles files with completely numeric names differently in some cases.
  
== Screenshots ==

1. Setting up the fields for the webform.
2. The webform in action.
3. Error message shown to user ( in a recent version of Firefox ).
4. Downloading the images and spreadsheet.

== Changelog ==

= 0.9.1 =
* fix a problem when deleting a field from a layout
* fix "copy layout" to copy the correct layout
* fix display of some error messages
* only ask for confirmation of a deletion if the item has been saved
* ensure an option field has at least 1 option before saving

== To Do ==

Enhancements planned eventually (in no particular order):

* Copy restrictions when copying a layout

* Override "max number of images" from Layouts in Webforms, so we don't need a whole new layout just to change the number of images

* Customize the "Upload closed" message

* Optional overall upload fields in addition to those attached to each image

* Checkbox and Text-area fields

* Possible option of a two-line row for each item in the webform, if the layout requires too much horizontal space

* Column sorting in the list view

* Some sort of archiving mechanism, so webforms that will no longer be used do not show up on the administration page, even if the images are still on the server.

* Internationalization?


