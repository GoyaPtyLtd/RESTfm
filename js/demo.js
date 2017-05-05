/**
 * RESTfm Demonstration Web Application.
 *
 * This demonstration code is free to use under the terms of the "MIT License"
 * as follows.
 *
 * Copyright (C) 2012 Goya Pty Ltd.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/**
 * Initialisation code executes the moment the DOM is ready for manipulation.
 */
$(document).ready(function() {

    // Setup behaviours for user actions.

    // Drop-down list selection.
    $('#database').change(function() { RESTfm.refreshLayout(); });
    $('#layout').change(function() { RESTfm.refreshResults(); });
    $('#findMax').change(function() { RESTfm.refreshResults(); });

    // Button events.
    $('.button').button();          // assign button functions to .button class
    $('#B_login').click(function() {
        if (RESTfm.isAuthenticated()) {
            RESTfm.resetForm();
        } else {
            RESTfm.loadCredentials();
            RESTfm.refreshDatabase();
        }
    });
    $('#B_options').click(function() { RESTfm.toggleOptionsDialog(); });
    $('#B_navRefresh').click(function() { RESTfm.refreshResults('last'); });
    $('#B_navStart').click(function() { RESTfm.refreshResults('start'); });
    $('#B_navPrev').click(function() { RESTfm.refreshResults('prev'); });
    $('#B_navNext').click(function() { RESTfm.refreshResults('next'); });
    $('#B_navEnd').click(function() { RESTfm.refreshResults('end'); });
    $('#B_createRecord').click(RESTfm.createRecordDialog);
    $('#B_updateRecord').click(RESTfm.updateRecordDialog);
    $('#B_deleteRecord').click(RESTfm.deleteRecordDialog);
    $('#B_refineSearch').click(RESTfm.refineSearchDialog);

    // Pressing Enter while in the password field clicks the Login button.
    $('input[name=password]').keypress(function(e) {
        if(e.which == 13) {
            jQuery(this).blur();
            jQuery('#B_login').focus().click();
        }
    });

    // Checkbox UI events.
    $('#option_console').click(function() {
        if ($(this).is(':checked')) {
            RESTfm.console.show();
            RESTfm.toggleOptionsDialog(false);
        } else {
            RESTfm.console.hide();
            RESTfm.toggleOptionsDialog(false);
        }
    });
    $('#option_useGet').click(function() { RESTfm.toggleOptionsDialog(false); });

    // Additional look and feel.

    // Button icons.
    $('#B_login').button('option', 'icons', {primary:'ui-icon-key'});
    $('#B_options').button('option', 'icons', {primary:'ui-icon-gear'});
    $('#B_options').button('option', 'text', false);
    $('#B_navRefresh').button('option', 'icons', {primary:'ui-icon-refresh'});
    $('#B_navRefresh').button('option', 'text', false);
    $('#B_navStart').button('option', 'icons', {primary:'ui-icon-seek-first'});
    $('#B_navStart').button('option', 'text', false);
    $('#B_navEnd').button('option', 'icons', {secondary:'ui-icon-seek-end'});
    $('#B_navEnd').button('option', 'text', false);
    $('#B_navPrev').button('option', 'icons', {primary:'ui-icon-seek-prev'});
    $('#B_navPrev').button('option', 'text', false);
    $('#B_navNext').button('option', 'icons', {secondary:'ui-icon-seek-next'});
    $('#B_navNext').button('option', 'text', false);
    $('#B_createRecord').button('option', 'icons', {primary:'ui-icon-document'});
    $('#B_createRecord').button('option', 'text', false);
    $('#B_updateRecord').button('option', 'icons', {primary:'ui-icon-pencil'});
    $('#B_updateRecord').button('option', 'text', false);
    $('#B_deleteRecord').button('option', 'icons', {primary:'ui-icon-trash'});
    $('#B_deleteRecord').button('option', 'text', false);
    $('#B_refineSearch').button('option', 'icons', {primary:'ui-icon-search'});
    $('#B_refineSearch').button('option', "text", false);

    // Clear default "JavaScript required." in status bar.
    $("#status_bar").removeClass('color_error').html('');

    // Show throbber during ajax queries.
    $('#throbber').ajaxStart(function () { $(this).css('visibility', 'visible'); });
    $('#throbber').ajaxStop (function () { $(this).css('visibility', 'hidden'); });


    RESTfm.resetForm();

    // Try to query databases list immediately. May fail if auth required.
    RESTfm.fastAuth = true;
    RESTfm.refreshDatabase();

});

/**
 * Extend jQuery to include htmlInfo UI style.
 */
(function($){
     $.fn.htmlInfo = function(message){
        return this.each(function(){
           var html = '<div class="ui-widget">';
           html += '<div class="ui-state-highlight ui-corner-all" style="padding: 0 .7em;">';
           html += '<p>';
           html += '<span class="ui-icon ui-icon-info" style="float:left; margin-right: .3em;"></span>';
           html += message;
           html += '</p>';
           html += '</div>';
           html += '</div>';

           $(this).html(html);
        });
     }
})(jQuery);

/**
 * Extend jQuery to include htmlAlert UI style.
 */
(function($){
     $.fn.htmlAlert = function(message){
        return this.each(function(){
           var html = '<div class="ui-widget">';
           html += '<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;">';
           html += '<p>';
           html += '<span class="ui-icon ui-icon-alert" style="float:left; margin-right: .3em;"></span>';
           html += message;
           html += '</p>';
           html += '</div>';
           html += '</div>';

           $(this).html(html);
        });
     }
})(jQuery);

// RESTfm namespace.
var RESTfm = RESTfm || {};

// RESTfm variables.
RESTfm.fastAuth = false;    // True when attempting immediate auth on DOM load.
RESTfm.authUser = '';
RESTfm.authPass = '';
RESTfm.version = '%%VERSION%%';
RESTfm.authenticated = false;
RESTfm.navURIs = {};        // Populated in refreshResults().
RESTfm.fieldNames = null;   // List of fieldNames (from refreshResults).
RESTfm.recordURIs = {};    // Associative array of recordID => href.
RESTfm.selectedID = null;   // RecordID user has selected to Delete/Update.
RESTfm.searchCriteria = {}; // Associative array of fieldName => criteria.


/**
 * Reset form back to clean state.
 */
RESTfm.resetForm = function() {
    RESTfm.authenticated = false;
    RESTfm.navURIs = {};
    RESTfm.fieldNames = null;
    RESTfm.recordURIs = {};
    RESTfm.selectedID = null;
    RESTfm.searchCriteria = {};

    $('#database').attr('disabled', '');
    $('#database').html('');
    $('#database_selector').addClass('color_disabled');

    $('#layout').attr('disabled', '');
    $('#layout').html('');
    $('#layout_selector').addClass('color_disabled');

    $('#findMax').attr('disabled', '');
    $('#findMax_selector').addClass('color_disabled');

    $('#results').html('');
    $('#status_bar').html('');

    $('#B_login').button('option', 'label', 'Login');
    //$('.hide_on_auth').fadeTo('fast', 1).css('visibility', 'visible');
    $('.hide_on_auth').slideDown();

    RESTfm.console.append('-- State Reset --');
}

/**
 * Toggle options dialogue.
 *
 * @param display
 *      Optional. Set true to display. Toggle visibility if undefined.
 */
RESTfm.toggleOptionsDialog = function(display) {
    // Toggle display if undefined.
    if (typeof display == 'undefined') {
        display = true;
        if ($('#D_options').dialog('isOpen') == true) {
            display = false;
        }
    }

    if (display) {
        // Position dialogue at options button.
        position =  $('#B_options').offset();
        top_adjust = $('#B_options').outerHeight() + 4;
        left_adjust = $('#B_options').outerWidth() - 7;
        width = 300;
        $('#D_options').dialog({
            title: 'Options',
            draggable: false,
            position: [position.left + left_adjust - width, position.top + top_adjust],
            width: width,
            resizable: false,
        });
    } else {
        $('#D_options').dialog('close');
    }
}

/**
 * RESTfm.console static class.
 */
RESTfm.console = {
    /**
     * Append string (wrapped in span) to console box with optional css class
     * applied. A break will be appended to the final string.
     *
     * @param content
     *      Content to append.
     * @param contentClass
     *      Optional css class to assign.
     */
    append: function(content, contentClass) {
        spanStart = '';
        if (typeof contentClass == 'undefined') {
            spanStart = '<span>';
        } else {
            spanStart = '<span class="' + contentClass + '">';
        }

        $('#console_box').append(spanStart + content + '</span><br>');
        // $('#console_box').animate({ scrollTop: $('#console_box').prop('scrollHeight') }, 1000);
        $('#console_box').scrollTop($('#console_box').prop('scrollHeight'));
    },

    appendXhrStatus: function(xhr) {
        this.append('&lt; ' + xhr.status + ' ' + xhr.statusText);
    },

    appendXhrStatusError: function(xhr) {
        this.append('&lt; ' + xhr.status + ' ' + xhr.statusText + ': '
                    + xhr.responseText, 'color_error');
    },

    reset: function() {
        $('#console_box').html('-- Console --<br>');
    },

    show: function () {
        $('#console_box').slideDown('fast');
        $('#console_box').scrollTop($('#console_box').prop('scrollHeight'));
    },

    hide: function () {
        $('#console_box').slideUp('fast');
    },
}

/**
 * Emulate PHP's htmlspecialchars.
 */
RESTfm.htmlspecialchars = function(text) {
  return text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
}

/**
 * Emulate PHP's htmlspecialchars_decode.
 */
RESTfm.htmlspecialchars_decode = function(text) {
  return text
      .replace(/&amp;/g,    '&')
      .replace(/&lt;/g,     '<')
      .replace(/&gt;/g,     '>')
      .replace(/&quote;/g,  '"')
      .replace(/&#039;/g,   "'");
}

/**
 * Encode double quotes.
 */
RESTfm.encodeQuotes = function(text) {
  return text
      .replace(/"/g, '&quot;');
}

/**
 * Handle authentication failures.
 */
RESTfm.authenticationFailed = function () {

    // Special case when attempting fast auth on DOM load. Useful where
    // an empty username and password is sufficient. Only skip the alert
    // dialogue the once.
    if (RESTfm.fastAuth) {
        RESTfm.fastAuth = false;
        RESTfm.resetForm();
        return;
    }

    // Clear password field.
    $('input[name=password]').val('');

    RESTfm.resetForm();

    RESTfm.console.append('Authentication failed.', 'color_error');

    $('#D_info').htmlAlert('Authentication failed.');
    $('#D_info').dialog({
        title: 'Authentication failed.',
        modal: true,
        buttons: {
            Continue: function() {
                $(this).dialog('close');
            }
        }
    });
}

/**
 * Show alert dialogue for XHR ajax errors.
 */
RESTfm.alertXhrStatusError = function(xhr) {
    var fmStatus = xhr.getResponseHeader('X-RESTfm-FM-Status');
    var fmReason = xhr.getResponseHeader('X-RESTfm-FM-Reason');

    $('#D_info').htmlAlert('Server has responded with an error.');

    if (fmStatus != null) {
        // FileMaker specific error, no need to show RESTfm status.
        $('#D_info').append('<p>' + 'FileMaker Error: ' + fmStatus + ' ' + fmReason );
    } else {
        // RESTfm specific error, only need to show RESTfm status.
        $('#D_info').append('<p>' + xhr.status + ' ' + xhr.statusText );
    }

    $('#D_info').dialog({
        title: 'Error',
        modal: true,
        buttons: {
            Close: function() {
                $(this).dialog('close');
            }
        }
    });
}

/**
 * Refresh the #database drop-down.
 */
RESTfm.refreshDatabase = function() {
    var databasesURI = '.json?RFMlink=layout';

    RESTfm.console.append('GET ' + databasesURI);

    // Fetch databases.
    $.ajax({
        url: databasesURI,
        dataType: 'json',
        beforeSend: function(xhr) {
            if (RESTfm.authUser != '') {
                xhr.setRequestHeader('Authorization', 'Basic ' +
                    Base64.encode(RESTfm.authUser + ':' + RESTfm.authPass));
            }
        },
    })
    .done(function(data, status, xhr) {
        var databaseOptions = [];

        RESTfm.checkVersion(xhr.getResponseHeader('X-RESTfm-Version'));

        databaseOptions.push('<option value="">-- Select --</option>');
        $.each(data['data'], function (index, row) {
            databaseOptions.push('<option value="' +
                data['meta'][index]['href'] + '">' +
                RESTfm.htmlspecialchars(row['database']) +
                '</option>');
        });
        //console.log(databaseOptions);
        $('#database').html(databaseOptions.join(''))
        //$('#database option:eq(0)').attr('selected', 'selected');
        $('#database').removeAttr('disabled');
        $('#database_selector').removeClass('color_disabled');

        //console.log(xhr);
        RESTfm.console.appendXhrStatus(xhr);

        RESTfm.setAuthenticated();
        RESTfm.refreshLayout();
    })
    .fail(function(xhr) {
        RESTfm.console.appendXhrStatusError(xhr);
        if (xhr.status == 403) {
            RESTfm.authenticationFailed();
        } else {
            RESTfm.alertXhrStatusError(xhr);
        }
    });
}

/**
 * Refresh the #layout drop-down.
 */
RESTfm.refreshLayout = function() {
    var layoutsURI = $('#database option:selected').val();
    if (layoutsURI == '' || layoutsURI == undefined) {
        return;
    }

    RESTfm.console.append('GET ' + layoutsURI);

    // Fetch layouts.
    $.ajax({
        url: layoutsURI,
        dataType: 'json',
        beforeSend: function(xhr) {
            if (RESTfm.authUser != '') {
                xhr.setRequestHeader('Authorization', 'Basic ' +
                    Base64.encode(RESTfm.authUser + ':' + RESTfm.authPass));
            }
        },
    })
    .done(function(data, status, xhr) {
        var layoutOptions = [];

        $.each(data['data'], function (index, row) {
            layoutOptions.push('<option value="' +
                data['meta'][index]['href'] + '">' +
                RESTfm.htmlspecialchars(row['layout']) +
                '</option>');
        });
        //console.log(layoutOptions);
        $('#layout').html(layoutOptions.join(''))
        //$('#layout option:eq(0)').attr('selected', 'selected');
        $('#layout').removeAttr('disabled');
        $('#layout_selector').removeClass('color_disabled');
        $('#findMax').removeAttr('disabled');
        $('#findMax_selector').removeClass('color_disabled');

        RESTfm.console.appendXhrStatus(xhr);

        RESTfm.refreshResults();
    })
    .fail(function(xhr) {
        RESTfm.console.appendXhrStatusError(xhr);
        if (xhr.status == 403) {
            RESTfm.authenticationFailed();
        } else {
            RESTfm.alertXhrStatusError(xhr);
        }
    });
}

/**
 * Refresh the #results table.
 *
 * @param browseTo
 *      Optional navigation string: start, end, prev, next or last.
 */
RESTfm.refreshResults = function(browseTo) {
    var baseURI = $('#layout option:selected').val();
    if (baseURI == '' || baseURI == undefined) {
        return;
    }
    baseURI += '?';
    RESTfm.startURI = baseURI;

    var findMax = '&RFMmax=' + $('#findMax option:selected').val();
    baseURI += findMax;

    var criteria = {};
    var criteriaNo = 0;
    $.each(RESTfm.searchCriteria, function (fieldName, val) {
        criteriaNo++;
        criteria['RFMsF' + criteriaNo] = RESTfm.htmlspecialchars_decode(fieldName);
        criteria['RFMsV' + criteriaNo] = val;
    });
    if (criteriaNo > 0) {
        baseURI += '&' + $.param(criteria);
    }

    var URI
    if (typeof browseTo == 'undefined') {
        URI = baseURI;                          // Default.
    } else if (typeof RESTfm.navURIs[browseTo] == 'undefined') {
        return;                                 // Unknown.
    } else {
        URI = RESTfm.navURIs[browseTo];         // We can do that.
    }
    //console.log('refreshResult: browseTo, URI: ', browseTo, URI);

    if (URI == '' || URI == null) {
        return;
    }

    RESTfm.console.append('GET ' + URI);

    // Fetch record data.
    $.ajax({
        url: URI,
        dataType: 'json',
        beforeSend: function(xhr) {
            if (RESTfm.authUser != '') {
                xhr.setRequestHeader('Authorization', 'Basic ' +
                    Base64.encode(RESTfm.authUser + ':' + RESTfm.authPass));
            }
        },
    })
    .done(function(data, status, xhr) {
        var tableData = [];

        // Identify field names and setup table headings first.
        RESTfm.fieldNames = [];
        tableData.push('<tr>');
        if (0 in data['data']) {    // Check we actually have data records.
            $.each(data['data'][0], function (fieldName, val) {
                tableData.push('<th>' +
                               RESTfm.htmlspecialchars(fieldName) +
                               '</th>');
                RESTfm.fieldNames.push(RESTfm.htmlspecialchars(fieldName));
                return true;        // Keeps strict warnings quiet.
            });
            tableData.push('</tr>');
        }
        //console.log('refreshResults data: ', data);
        //console.log('refreshResults fieldNames: ', RESTfm.fieldNames);

        // Populate table rows with record data.
        RESTfm.recordURIs = {};
        var row_num = 0;
        $.each(data['data'], function (index, row) {
            RESTfm.recordURIs[row_num] = data['meta'][index]['href'];

            if (row_num % 2 == 0) {
                tableData.push('<tr id="' + row_num + '" class="row_alt_color">');
            } else {
                tableData.push('<tr id="' + row_num + '">');
            }
            $.each(row, function (fieldName, val) {
                tableData.push('<td name="' +
                            RESTfm.htmlspecialchars(fieldName) + '">' +
                            '<pre>' + RESTfm.htmlspecialchars(val) + '</pre>' +
                            '</td>');
                return true;        // Keeps strict warnings quiet.
            });
            tableData.push('</tr>');
            row_num++;
        });
        $('#results').html(tableData.join('\n'))

        // Clicking on a row in the #results table will toggle selection.
        RESTfm.selectedID = null;
        $('#results tr').css( 'cursor', 'pointer' );
        $('#results tr').click(function() {
            var clickedID = $(this).attr('id');
            if (RESTfm.selectedID != null) {
                $('#' + RESTfm.selectedID).removeClass('row_highlight');
            }

            if (RESTfm.selectedID == clickedID) {
                RESTfm.selectedID = null;
            } else {
                RESTfm.selectedID = clickedID;
                $('#' + RESTfm.selectedID).addClass('row_highlight');
            }
        });

        // Show range of records returned in #status_bar field.
        var skip = parseInt(data['info']['skip']) + 1;
        var skipTo = skip + parseInt(data['info']['fetchCount']) - 1;
        $('#status_bar').html( 'Records: ' + skip + ' to '+ skipTo + ' of ' + data['info']['foundSetCount']);

        // Keep navigation URIs provided by RESTfm server.
        RESTfm.navURIs = {};
        $.each(data['nav'], function (name, href) {
            RESTfm.navURIs[name] = href;
        });

        // Remember the last successful URI.
        RESTfm.navURIs['last'] = URI;


        RESTfm.console.appendXhrStatus(xhr);
    })
    .fail(function(xhr) {
        RESTfm.console.appendXhrStatusError(xhr);
        if (xhr.status == 403) {
            RESTfm.authenticationFailed();
        } else {
            RESTfm.alertXhrStatusError(xhr);
        }
    });
}

/**
 * Check we don't have mismatched versions, which may cause compatibility
 * issues.
 */
RESTfm.checkVersion = function(serverVersion) {
    if (RESTfm.version == serverVersion) {
        return;
    }

    if (RESTfm.version.indexOf('VERSION') >= 0 &&
        serverVersion.indexOf('GIT') >= 0 ) {
        return;
    }

    $('#D_info').htmlAlert('Warning: RESTfm version mismatch!');
    $('#D_info').append("<br>\n" +
                          'Client version: ' + RESTfm.version + "<br>\n" +
                          'Server version: ' + serverVersion + "<br>\n"
                          );
    $('#D_info').append("<br>\n" +
                          '<b>Software may not function properly.</b>'
                          );
    $('#D_info').dialog({
        title: 'Version mismatch',
        modal: true,
        buttons: {
            Close: function() {
                $(this).dialog('close');
            }
        }
    });
}

/**
 * Set the "session" to authenticated.
 */
RESTfm.setAuthenticated = function() {
    RESTfm.authenticated = true;
    RESTfm.fastAuth = false;
    $('#B_login').button('option', 'label', 'Logout');
    //$('.hide_on_auth').fadeTo('fast', 0).css('visibility', 'hidden');
    $('.hide_on_auth').slideUp();
}

/**
 * Return the "session" state.
 */
RESTfm.isAuthenticated = function() {
    return RESTfm.authenticated;
}

/**
 * Load credentials from form fields.
 */
RESTfm.loadCredentials = function() {
    RESTfm.authUser = $('input[name=username]').val();
    RESTfm.authPass = $('input[name=password]').val();
}

/**
 * Show the Create Record Dialogue.
 */
RESTfm.createRecordDialog = function() {
    if (RESTfm.fieldNames == null) {
        return;
    }

    // Build dialogue content.
    var tableData = [];
    tableData.push('<table>');
    $.each(RESTfm.fieldNames, function (index, fieldName) {
        tableData.push(
            '<tr>' +
            '<td>' + fieldName + '</td>' +
            '<td><input type="text" name="' + fieldName + '"></td>' +
            '</tr>'
            );
        return true;        // Keeps strict warnings quiet.
    });
    tableData.push('</table>');
    $('#D_record_content').html(tableData.join('\n'))

    // Present dialogue.
    $('#D_record').dialog({
        title: 'Create Record',
        modal: true,
        buttons: {
            Create: function() {
                // Fetch data from dialogue form fields.
                var data = { 'data': [ {} ] };
                $('#D_record_content table input').each(function() {
                    var child = $(this);
                    if (child.is(':input')) {
                        data['data'][0][child.attr('name')] = child.val();
                    }
                });

                // Little hack to make submitData() refresh the results to
                // the end, where newly created records can be seen.
                RESTfm.navURIs['last'] = RESTfm.navURIs['end'];

                RESTfm.submitData('POST',
                                  $('#layout option:selected').val(),
                                  data, 'New record created.',
                                  $('#option_useGet').is(':checked'));
                $(this).dialog("close");
            }
        }
    });
}

/**
 * Show the Update Record Dialogue.
 */
RESTfm.updateRecordDialog = function() {
    if (RESTfm.fieldNames == null || RESTfm.selectedID == null) {
        return;
    }

    // Pull existing record data from selected row in #results table.
    var rowData = [];
    $('#' + RESTfm.selectedID + ' td pre').each(function() {
        rowData.push($(this).html());
    });

    // Build dialogue content.
    var tableData = [];
    var fieldNo = 0;
    tableData.push('<table>');
    $.each(RESTfm.fieldNames, function (index, fieldName) {
        tableData.push(
            '<tr>' +
            '<td>' + fieldName + '</td>' +
            '<td><input type="text" name="' + fieldName + '"' +
            ' value="' + RESTfm.encodeQuotes(rowData[fieldNo]) + '"></td>' +
            '</tr>'
            );
        fieldNo++;
        return true;        // Keeps strict warnings quiet.
    });
    tableData.push('</table>');
    $('#D_record_content').html(tableData.join('\n'))

    // Present dialogue.
    $('#D_record').dialog({
        title: 'Update Record',
        modal: true,
        buttons: {
            Update: function() {
                // Fetch data from dialogue form fields.
                var data = { 'data': [ {} ] };
                $('#D_record_content table input').each(function() {
                    var child = $(this);
                    if (child.is(':input')) {
                        data['data'][0][child.attr('name')] = child.val();
                    }
                });
                RESTfm.submitData('PUT',
                                  RESTfm.recordURIs[RESTfm.selectedID],
                                  data, 'Record updated.',
                                  $('#option_useGet').is(':checked'));
                $(this).dialog("close");
            }
        }
    });
}

/**
 * Show the Delete Record Dialogue.
 */
RESTfm.deleteRecordDialog = function() {
    if (RESTfm.fieldNames == null || RESTfm.selectedID == null) {
        return;
    }

    // Pull existing record data from selected row in #results table.
    var rowData = [];
    $('#' + RESTfm.selectedID + ' td pre').each(function() {
        rowData.push($(this).html());
    });

    // Build dialogue content.
    var tableData = [];
    var fieldNo = 0;
    tableData.push('<table>');
    $.each(RESTfm.fieldNames, function (index, fieldName) {
        tableData.push(
            '<tr>' +
            '<td>' + fieldName + '</td>' +
            '<td><input type="text" name="' + fieldName + '"' +
            ' value="' + rowData[fieldNo] +
            '" readonly="readonly" class="color_disabled_background"></td>' +
            '</tr>'
            );
        fieldNo++;
        return true;        // Keeps strict warnings quiet.
    });
    tableData.push('</table>');
    $('#D_record_content').html(tableData.join('\n'))

    // Present dialogue.
    $('#D_record').dialog({
        title: 'Delete Record',
        modal: true,
        buttons: {
            Delete: function() {
                RESTfm.submitData('DELETE',
                                  RESTfm.recordURIs[RESTfm.selectedID],
                                  null,
                                  'Record deleted.',
                                  $('#option_useGet').is(':checked'));
                $(this).dialog("close");
            }
        }
    });
}

/**
 * Submit provided data via ajax using the provided method and URI.
 *
 * @param method
 *      HTTP method to use: POST, GET, PUT, or DELETE.
 * @param URI
 *      URI to submit data to.
 * @param data
 *      Associative array object in the form: { 'data': [ {} ] }
 *      When passed to JSON.stringify, will result in a RESTfm compatible
 *      JSON associative array.
 *      Example for two rows of record data:
 *      var stuff = { 'data': [
 *                      { 'FieldName1': 'somedata',             // row 1
 *                        'FieldName2': 'moredata' },
 *                      { 'FieldName1': 'datadata',             // row 2
 *                        'FieldName2': 'Data, please come to the bridge.'},
 *                    ] };
 * @param successString
 *      The string to present to the user on successful submission of data.
 * @param boolean getMethodOverride
 *      true or false. Actually use GET for all methods, and instruct RESTfm to
 *      override with the desired method.
 *      Some limitations with this technique:
 *        - Length of query string is limited to support in browser and server.
 *        - Field names may not begin with RFM, these are RESTfm reserved words.
 *        - Only the first row of data may be sent.
 *      This technique should only be used when absolutely necessary, much
 *      better to use POST, PUT, DELETE directly if possible.
 */
RESTfm.submitData = function(method, URI, data, successString, getMethodOverride) {
    //console.log('submitData: ', method, URI, data, successString);
    //console.log('JSON.stringfy data: ', JSON.stringify(data));
    //console.log('JQuery.param data[0]: ', $.param(data['data'][0]));

    // Check if we are using GET for all methods.
    if (getMethodOverride == true) {
        // Instruct RESTfm of the desired override method.
        queryString = 'RFMmethod=' + method;

        // Encode the first line of data as a query string.
        if (data != null) {
            queryString += '&' + $.param(data['data'][0]);
            data = null;
        }

        // Drop back to the GET method.
        method = 'GET';

        // All data gets sent in query string when using GET.
        URI += '?' + queryString;
    } else if (data != null) {
        data = JSON.stringify(data);
    }

    RESTfm.console.append(method + ' ' + URI);
    $.ajax({
        url: URI,
        dataType: 'json',
        contentType: 'application/json',
        type: method,
        processData: false,
        data: data,
        beforeSend: function(xhr) {
            if (RESTfm.authUser != '') {
                xhr.setRequestHeader('Authorization', 'Basic ' +
                    Base64.encode(RESTfm.authUser + ':' + RESTfm.authPass));
            }
        },
    })
    .done(function(responseData, status, xhr) {
        //console.log('submitData response: ', responseData);
        RESTfm.console.appendXhrStatus(xhr);
        RESTfm.refreshResults('last');
        $('#D_info').htmlInfo(successString);
        $('#D_info').dialog({
            title: 'Success',
            modal: true,
            buttons: {
                OK: function() {
                    $(this).dialog('close');
                }
            }
        });
    })
    .fail(function(xhr) {
        RESTfm.console.appendXhrStatusError(xhr);
        if (xhr.status == 403) {
            RESTfm.authenticationFailed();
        } else {
            RESTfm.alertXhrStatusError(xhr);
        }
    });
}

/**
 * Show the Refine Search Dialogue.
 */
RESTfm.refineSearchDialog = function() {
    if (RESTfm.fieldNames == null) {
        return;
    }

    // Build dialogue content.
    var tableData = [];
    tableData.push('<table>');
    $.each(RESTfm.fieldNames, function (index, fieldName) {
        tableData.push(
            '<tr>' +
            '<td>' + fieldName + '</td>' +
            '<td><input type="text" name="' + fieldName + '" value="' +
                (RESTfm.searchCriteria[fieldName] == undefined ? "" : RESTfm.searchCriteria[fieldName]) +
                '"></td>' +
            '</tr>'
            );
        return true;        // Keeps strict warnings quiet.
    });
    tableData.push('</table>');
    tableData.push('<p>Use values like: "<100" or "=John Smith"</p>');
    $('#D_record_content').html(tableData.join('\n'))

    // Present dialogue.
    $('#D_record').dialog({
        title: 'Refine Search',
        modal: true,
        buttons: {
            'Perform Find': function() {
                // Fetch search criteria from dialogue form fields.
                RESTfm.searchCriteria = {};
                $('#D_record_content table input').each(function() {
                    var child = $(this);
                    if (child.is(':input')) {
                        if (child.val() != "") {
                            RESTfm.searchCriteria[child.attr('name')] = child.val();
                        }
                    }
                });
                RESTfm.refreshResults();
                $(this).dialog("close");
            }
        }
    });
}
