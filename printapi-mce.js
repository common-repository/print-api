/*
License: GPL2

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program. If not, see <ttp://www.gnu.org/licenses/.
*/

(function () {

    tinymce.create( 'tinymce.plugins.printapi', {
        init: function( editor, url ) {
            editor.addButton( 'printapi' , {
                text: 'Print API',
                image: false,
                onclick: function() {
                    editor.windowManager.open({
                        title: 'Bestelknop invoegen',
                        body: [
                            { type: 'container', html: '<p>Kopieer en plak een plugin code uit je Print API account.</p>' },
                            { type: 'textbox', name: 'code', label: 'Plugin code' },
                        ],
                        onsubmit: function ( e ) {
                            editor.insertContent( '[printapi code="' + e.data.code + '"]' );
                        }
                    });
                }
            });
        }
    });

    tinymce.PluginManager.add( 'printapi', tinymce.plugins.printapi );

}());