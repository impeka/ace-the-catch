jQuery(document).ready(function(){
	init_toggler();
	init_repeater();
	init_code_copy();
	init_export_csv();
	init_download_tickets();
	init_misc();
});

function init_misc() {
	//limit envelope values in backend
	if( jQuery('#envelope_amount')[0] ) {
		jQuery('#envelope_amount').change(function(){
			jQuery('input[name^="envelope_number"]').attr('max',jQuery(this).val());
		});
	}
}

function init_code_copy() {
	if( jQuery('[data-copy]')[0] ) {
		jQuery('[data-copy]').click(function(){
			var target = jQuery(this).data('copy');
			if (document.selection) { 
				var range = document.body.createTextRange();
				range.moveToElementText(document.getElementById(target));
				range.select().createTextRange();
				document.execCommand("copy"); 
			} 
			else if (window.getSelection) {
				var range = document.createRange();
				range.selectNode(document.getElementById(target));
				window.getSelection().addRange(range);
				document.execCommand("copy");
			}
		});
	}
}

function init_toggler() { //tried to make a more global one
	if( jQuery('.toggle-trigger')[0] ) {

		jQuery('form#post').submit(function(e) {
			jQuery(this).find('li[data-toggle-cond]:not(:visible)').remove();
		});

		jQuery('.toggle-trigger').change(function() {
			var selector = jQuery(this).attr('id')+'-'+jQuery(this).val();
			jQuery('*[data-toggle-cond^="'+jQuery(this).attr('id')+'-"').hide();
			
			if( jQuery(this).val() != '' )
				jQuery('*[data-toggle-cond^="'+selector+'"').show();


		}).trigger('change');
	}
}

function init_repeater() {
	if( jQuery('button[data-repeater]')[0] ) {
		jQuery('button[data-repeater]').click(function() {
			
			var type = jQuery(this).data('repeater');
			var template = jQuery('[data-repeater-template="'+type+'"]');

			var	clone = template.clone();
			var id = 0;

			if( jQuery('.'+type)[0] ) {
				jQuery('.'+type).each(function(){
					var curId = jQuery(this).find('[name^="'+type+'["]').val();
					if( curId > id ) {
						id = parseInt( curId );
					}
				});
			}

			id++;

			clone = clone[0].outerHTML.replaceAll( '{ID}', id );
			clone = jQuery(clone);
			clone.removeAttr('data-repeater-template').addClass(type).appendTo('[data-repeater-container="'+type+'"]').find('.wysiwyg').each(function(){
				wp.editor.initialize( this.id );
				jQuery(this).removeClass('wysiwyg');
			});
			
		});
	}

	jQuery('form[name="post"]').submit(function(){
		jQuery('[data-repeater-template]').remove();
	});
}

String.prototype.replaceAll = function(search, replacement) {
    var target = this;
    return target.split(search).join(replacement);
};

function init_export_csv() {
	if( jQuery('[data-export-csv]')[0] ) {
		jQuery('[data-export-csv]').click(function(e){
			e.preventDefault();

			jQuery.post(
				lotto.ajax,
				{
					action : 'export_csv',
					lotto_id : jQuery(this).data('export-csv'),
					from : jQuery('#export_start').val(),
					to : jQuery('#export_end').val(),
					include_header : 'yes' 
				},
				function( response ) {
					if( !response.success ) {
						Swal.fire({
							type: 'error',
							title: 'Oops...',
							html: response.message
						});
					}
					else {
						//response.message
						exportCSVFile( {}, response.message, 'ticket-export' );
					}
				},
				'json'
			);

		});
	}
}

function init_download_tickets() {
	if( jQuery('[data-print-tickets]')[0] ) {
		jQuery('[data-print-tickets]').click(function(e){
			e.preventDefault();

			jQuery.post(
				lotto.ajax,
				{
					action : 'export_csv',
					lotto_id : jQuery(this).data('print-tickets'),
					from : jQuery('#export_start').val(),
					to : jQuery('#export_end').val(),
					include_header : false
				},
				function( response ) {
					if( !response.success ) {
						Swal.fire({
							type: 'error',
							title: 'Oops...',
							html: response.message
						});
					}
					else {
						//response.message
						var height_adjust = 150; //I don't understand why jsPDF is so inaccurate with its coordinates
						height_adjust = 0;
						var doc = new jsPDF();
						var filename = 'tickets-'+jQuery('#export_start').val()+'-'+jQuery('#export_end').val()+'.pdf';
						
						var maxLength = 11 * 29; // lines * characters per line

						var width = doc.internal.pageSize.getWidth();
						var height = doc.internal.pageSize.getHeight();

						var tickets_per_page = 15;

						var pdf_bg_img = new Image();
						pdf_bg_img.src = lotto.pdf;

						var tickets = response.message;

						var tickets_positions = [
							{
								x : 100,
								y : 120
							},
							{
								x : 950,
								y : 120
							},
							{
								x : 1800,
								y : 120
							},
							{
								x : 100,
								y : 840
							},
							{
								x : 950,
								y : 840
							},
							{
								x : 1800,
								y : 840
							},
							{
								x : 100,
								y : 1560
							},
							{
								x : 950,
								y : 1560
							},
							{
								x : 1800,
								y : 1560
							},
							{
								x : 100,
								y : 2280
							},
							{
								x : 950,
								y : 2280
							},
							{
								x : 1800,
								y : 2280
							},
							{
								x : 100,
								y : 3020
							},
							{
								x : 950,
								y : 3020
							},
							{
								x : 1800,
								y : 3020
							}
						];
						
						
						pdf_bg_img.onload = function() {
							var ratio = this.width / width;

							doc.addImage(lotto.pdf, 'PNG', 0, 0, width, height);
							doc.setFont("courier");
							doc.setFontSize(9);
							
							var ticket_num = 1;
							var line = '';

							for ( var key in tickets ) {
								if (tickets.hasOwnProperty( key ) ) {

									if( key != 0 && key % tickets_per_page == 0 ) {
										doc.addPage();
										doc.addImage(lotto.pdf, 'PNG', 0, 0, width, height);
									}
									//console.log( key + ' ' + tickets[key].label );
									line = ('Ticket: '+tickets[key].ticket_number).replace(/(.{29})/g, "$1\r\n");
									line += "\r\n"+('Envelope: '+tickets[key].envelope_number).replace(/(.{29})/g, "$1\r\n");
									line += "\r\n"+('Name: '+tickets[key].fname+' '+tickets[key].lname).replace(/(.{29})/g, "$1\r\n");
									line += "\r\n"+('Tel: '+tickets[key].telephone).replace(/(.{29})/g, "$1\r\n");
									line += "\r\n"+('Email: '+tickets[key].email).replace(/(.{29})/g, "$1\r\n");
									//line += "\r\n"+('ABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZ').replace(/(.{29})/g, "$1\r\n");

									line = line.substring(0, maxLength);

									doc.text( line, (tickets_positions[key%tickets_per_page].x/ratio), (tickets_positions[key%tickets_per_page].y/ratio)+(height_adjust/ratio) );

									ticket_num++;
								}
							}

							doc.save(filename);
						}
					}
				},
				'json'
			);

		});
	}
}

function convertToCSV(objArray) {
    var array = typeof objArray != 'object' ? JSON.parse(objArray) : objArray;
    var str = '';
    var value = '';

    for (var i = 0; i < array.length; i++) {
        var line = '';
        for (var index in array[i]) {
            if (line != '') line += ',';

            value = array[i][index].toString().replace(/"/g, '\\"');
			value = array[i][index].toString().replace(/\\/g, '');
			value = array[i][index].toString().replaceAll("’", "'");
			value = array[i][index].toString().replaceAll("\’", "'");
			
			if( value.includes('Amour') ) {
				console.log(value);
			}

            line += '"'+value+'"';
        }

        str += line + '\r\n';
    }

    return str;
}

function exportCSVFile(headers, items, fileTitle) {
    if (headers) {
        //items.unshift(headers);
    }

    // Convert Object to JSON
    var jsonObject = JSON.stringify(items);

    var csv = convertToCSV(jsonObject);

    var exportedFilenmae = fileTitle + '.csv' || 'export.csv';

    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, exportedFilenmae);
    } else {
        var link = document.createElement("a");
        if (link.download !== undefined) { // feature detection
            // Browsers that support HTML5 download attribute
            var url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", exportedFilenmae);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}