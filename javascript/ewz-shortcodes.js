'use strict';
var  ewz_menu = [], k, webform, identlist;
         for ( k=0; k < EWZdata.webforms.length; ++k ) {
             webform = EWZdata.webforms[k];
             var shrt = '[ewz_show_webform  identifier="' + webform.webform_ident + '"]';
             ewz_menu.push( {         text: webform.webform_title, 
                              ewz_shrtcode: shrt,
                                   onclick: function() {  tinymce.execCommand( 'mceInsertContent', false, this.settings.ewz_shrtcode); }
                            });
         }
         identlist = "";
         for ( k=0; k < EWZdata.webforms.length; ++k ) { 
             if( identlist.length > 0 ){
                 identlist += ',';
             }
             identlist += EWZdata.webforms[k].webform_ident;
         }
         
         ewz_menu.push(  { text: '"Followup" Form',
                           ewz_identlist: identlist,
                           onclick : function() {
                               alert("Displays to each user a read-only summary of what they uploaded using the listed webforms. \n\nBy default, it includes all webforms. Edit the 'idents' to remove some, being careful to keep the commas correct and leave no spaces. \n\nBy default, it includes all possible admin-uploaded data.  Edit the 'show' list to remove.");
                               tinymce.execCommand( 'mceInsertContent',false, '[ewz_followup idents="' + this.settings.ewz_identlist + '" show="title,excerpt,content,item_data"]');                  
                           }} );

(function() {
    tinymce.create('tinymce.plugins.EWZShortcodes', {
        init: function(editor) { 
               
            editor.addButton( 'ewz_shortcodes', {
                type: 'menubutton',
                text: 'EWZ Shortcodes  ',
                icon: false,
                onselect: function() {}, 
                menu: ewz_menu
            });
     }
});
tinymce.PluginManager.add( 'ewz_shortcodes', tinymce.plugins.EWZShortcodes );
})();
