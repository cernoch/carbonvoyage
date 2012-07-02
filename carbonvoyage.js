jQuery(function() {
	i = 0;
	jQuery("a.carbonfootprint").each(function() {
		index = ++i;
		toto = jQuery(this);
		form = toto.after(
			"<div id='cv_"+i+"' class='carbonvoyage'>"+
				"<div class='cv_input cv_beg'>"+
					"<label for='cv_beg_"+i+"'>From:</label>"+
					"<input id='cv_beg_"+i+"' name='cv_beg' class='cv_field'/>"+
				"</div>"+
				"<div class='cv_input cv_end'>"+
					"<label for='cv_end_"+i+"'>To:</label>"+
					"<input id='cv_end_"+i+"' name='cv_end' class='cv_field'"+
						" value='"+jQuery(this).attr("data-destination")+"'/>"+
				"</div>"+
				"<div class='cv_input cv_submit'>"+
					"<button class='cv_field'>Calculate CO<sub>2</sub></button>"+
				"</div>"+
			"</div>").next();
		toto.remove();

		var resolveLocation = function() {
			beg = jQuery("input[name=cv_beg]",form).val();
			end = jQuery("input[name=cv_end]",form).val();
			
			bsn = beg.search(/[0-9\. ]+/) == -1;
			esn = end.search(/[0-9\. ]+/) == -1;
			
			jQuery(".cv_result",form).remove();
			jQuery.getJSON(carbonVoyageEndPoint+
					"?from="+escape(beg)+
					"&to="+escape(end)+
					"&from_sensor="+bsn+
					"&to_sensor="+esn,

				function(data) {
					jQuery("select",form).remove();
					jQuery("div.cv_result",form).remove();
					res = jQuery(form)
						.append("<div class='cv_result'></div>")
						.children().last();
					
					if (data['message'] != null) {
						res.html(data['message']);
						return;
					} 
					jQuery("div.cv_attribution",form).remove();
					jQuery(form).append("<div class='cv_attribution'>Using the"
						+ " <a href='https://developers.google.com/maps/'>"
						+ "Google Maps API</a>. CO<sub>2</sub> methodology is"
						+ " taken from <a href='http://www.transportdirect.info"
						+ "/Web2/JourneyPlanning/JourneyEmissionsCompare.aspx'>"
						+ "TransportDirect</a>.</div>");


					hidden = jQuery("div.cv_beg input, div.cv_end input, div.cv_submit button",form);
					hidden.hide();
					
					bsl = jQuery("div.cv_beg",form).append("<select name='cv_beg"+index+"' class='cv_field'></select>").children().last();
					esl = jQuery("div.cv_end",form).append("<select name='cv_end"+index+"' class='cv_field'></select>").children().last();
					cls = jQuery("div.cv_submit",form).append("<button name='cv_reset' class='cv_field'>Reset</button>").children().last();
					
					for (f in data['from'])
						bsl.append("<option value='"+f+"'>"
							+data['from'][f]['name']+"</option>");
					
					for (t in data['to'])
						esl.append("<option value='"+t+"'>"
							+data['to'][t]['name']+"</option>");
					
					cls.click(function() {
						res.remove();
						bsl.remove();
						esl.remove();
						cls.remove();
						hidden.show();
					});

					var updateCO2 = function() {
						id = data['to'].length * (jQuery(bsl).val() - 0)
						                       + (jQuery(esl).val() - 0);

						airDist = data['air'][id]['distance'];
						carDist = data['car'][id]['distance'];
						if (data['car'][id]['message'] != "OK")
							carDist = airDist;

						dim = "<div class='cv_dimension'> kg CO<sub>2</sub></div>";
						res.children().remove();

						coach=res.append("<div class='cv_graph cv_coach'></div>").children().last();
						coach.append("<div class='cv_label'>Coach</div>");
	 					coach.append("<div class='cv_field'></div>").children().last().html(Math.round(carDist * 0.03) + dim);
						coach.append("<div class='cv_bar'>&nbsp;</div>").children().last().css("width", 100 * 0.03 + "em");

						train=res.append("<div class='cv_graph cv_train'></div>").children().last();
						train.append("<div class='cv_label'>Train</div>");
						train.append("<div class='cv_field'>&nbsp;</div>").children().last().html(Math.round(carDist * 0.0534) + dim);
						train.append("<div class='cv_bar'>&nbsp;</div>").children().last().css("width", 100 * 0.0534 + "em");

						plane=res.append("<div class='cv_graph cv_plane'></div>").children().last();
						plane.append("<div class='cv_label'>Plane</div>");
						plane.append("<div class='cv_field'></div>").children().last().html(Math.round(airDist * 0.1715) + dim);
						plane.append("<div class='cv_bar'>&nbsp;</div>").children().last().css("width", 100 * 0.1715 + "em");

						car=res.append("<div class='cv_graph cv_car'></div>").children().last();
						car.append("<div class='cv_label'>Car</div>");
						car.append("<div class='cv_field'></div>").children().last().html(Math.round(carDist * 0.03 * 4.3) + dim);
						car.append("<div class='cv_bar'>&nbsp;</div>").children().last().css("width", 100 * 0.03 * 4.3 + "em");
                                                car.append("<div class='cv_comment'>~ large car with 1 person ~ small car with 2 persons</div>");

						info=res.prepend("<div class='cv_box'>You would be travelling"
							+ " <div class='cv_field cv_air'></div> by plane or"
							+ " <div class='cv_field cv_car'></div> by car.</div>")
							.children().first();

						jQuery(".cv_field.cv_air",info).html(Math.round(airDist));
						jQuery(".cv_field.cv_car",info).html(Math.round(carDist));
						jQuery(".cv_field",info).append("<div class='cv_dimension'> km</div>");
						if (data['car'][id]['message'] != "OK")
							jQuery(".cv_field.cv_car").prepend("approximately ");
						};
						updateCO2();
						bsl.change(updateCO2);
						esl.change(updateCO2);
				}
			);
		};

		jQuery("button",form).click(resolveLocation);
		if (navigator.geolocation) {
			function setOrigin(position) {
				jQuery("input[name=cv_beg]", form).attr("value",
					position.coords.latitude + " " + position.coords.longitude); 
				resolveLocation();
			}
			navigator.geolocation.getCurrentPosition(setOrigin);
		}         
	});
});
