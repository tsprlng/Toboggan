/**
	Holds the JS used for the administration system
*/
(function(){
	var apikey = '{05C8236E-4CB2-11E1-9AD8-A28BA559B8BC}',
		apiversion = '0.6',
		initialProgressEvent = false,	//used to ensure that the initial progress event is the only one handled
		playerCSSProperties = {},
		rightClickedObject = {},
		currentUserName = "",
		currentUserID = "",
		ajaxCache = {
			fileTypeSettings : false,
			commandSettings : false,
			fileConverterSettings : false
		},
		converterSettings = {
			commands: {},
			fileTypes: {},
			converters : {}
			};
	/**
		jQuery Entry Point
	*/
	$(document).ready(function(){
		doLogin();
	});

	/**
		display and handle the login form if required
	*/
	function doLogin()
	{
		$("#loginForm").keypress(function(e) {
			if(e.which === 13)
			{
				ajaxLogin();
				e.preventDefault();
			}
		});
		//present the login form:
		$("#loginFormContainer").dialog({
			autoOpen: true,
			modal: true,
			title: 'Login',
			buttons: {
				'Login': ajaxLogin
			}
		});
	}

	function ajaxLogin()
	{
		var hash = new jsSHA($("#passwordInput").val()).getHash("SHA-256","B64");
		$.ajax({
			url:'backend/rest.php?action=login&apikey='+apikey+"&apiver="+apiversion,
			type: 'POST',
			data: {
				'username': $("#username").val(),
				'password': hash
			},
			success: function(data,textStatus,jqHXR){
				var allowedAdminLogin=false;
				for (var x=0; x < data.permissions.length; ++x)
				{
					//3 == is Administrator
					if(data.permissions[x].id==3 && data.permissions[x].granted=='Y')
					{
						allowedAdminLogin=true;
						break;
					}
				}
				
				if(allowedAdminLogin)
				{
					$("#loginFormContainer").dialog("close");
					displayConfig();
				}
				else
				{
					alert("Administrative access has not been granted by the remote server");
				}
			},
			error: function(jqhxr,textstatus,errorthrown){
				console.debug(jqhxr,textstatus,errorthrown);
				alert("Login Failed");							
			}
		});
		
	}
	
	/******************************************************************
		Configuration Functions
	*******************************************************************/
	function displayConfig(event)
	{	
		if($("#configDialog").length==0)
			$("<div id='configDialog' />")
				.append(
					$("<ul id='configTabs'/>")
						.append($("<li><a href='#tab_welcome'>Welcome</a></li>"))
			//			.append($("<li><a href='#tab_client'>Client</a></li>"))
						.append($("<li><a href='#tab_server_streamers'>Streamers</a></li>"))
						.append($("<li><a href='#tab_server_users'>Users</a></li>"))
						.append($("<li><a href='#tab_server_mediaSources'>Media Sources</a></li>"))
						.append($("<li><a href='#tab_server_log_contents'>View Server Log</a></li>"))
				)
				.append(
					$("<div id='tab_welcome'></div>")
						.append($("	<h1>Toboggan Maintenance Page</h1> \
									<p>Please select from the tabs at the top of the page to chose which facet of Toboggan to configure</p>"))
				)
				.append(
					$("<div id='tab_server_streamers'></div>")
				)
				.append(
					$("<div id='tab_server_users'></div>")
				)
				.append(
					$("<div id='tab_server_mediaSources'></div>")
				)
				.append(
					$("<div id='tab_server_log_contents'>\
						<h1>The last <input type='number' name='serverLogSize' min='1' max='100' id='serverLogSize' value='5' class='inlineInput'/> KiB of the Server Log <span class='refresh'>Update</span></h1>\
						<pre id='server_log_contents_target' ></pre></div>")
				)
				.appendTo("body");
		
		$("#configDialog").tabs({
			selected: 0,
			select: function(event, ui){
				
				//TODO: display loading placeholder here
				switch(ui.panel.id)
				{
					case 'tab_server_streamers':
						$(ui.panel).empty();
						$(ui.panel).append("<h1>Add/Remove Streamers</h1>");
						
						//pull down retrieveFileTypeSettings
						$.ajax({
							url: g_Toboggan_basePath+"/backend/rest.php"+"?action=retrieveFileTypeSettings&apikey="+apikey+"&apiver="+apiversion,
							success: function(data, textStatus, jqHXR) {
								ajaxCache.fileTypeSettings = data;
								prepareConverters();
							},
							error: function(jqHXR, textStatus, errorThrown) {
								alert("An error occurred while retrieveFileTypeSettings");
								console.error(jqHXR, textStatus, errorThrown);
							}
						});

						//pull down retrieveCommandSettings
						$.ajax({
							url: g_Toboggan_basePath+"/backend/rest.php"+"?action=retrieveCommandSettings&apikey="+apikey+"&apiver="+apiversion,
							success: function(data, textStatus, jqHXR) {
								ajaxCache.commandSettings = data;
								prepareConverters();
							},
							error: function(jqHXR, textStatus, errorThrown) {
								alert("An error occurred while retrieveCommandSettings");
								console.error(jqHXR, textStatus, errorThrown);
							}
						});

						//pull down retrieveFileConverterSettings
						$.ajax({
							url: g_Toboggan_basePath+"/backend/rest.php"+"?action=retrieveFileConverterSettings&apikey="+apikey+"&apiver="+apiversion,
							success: function(data, textStatus, jqHXR) {
								ajaxCache.fileConverterSettings = data;
								prepareConverters();
							},
							error: function(jqHXR, textStatus, errorThrown) {
								alert("An error occurred while retrieveFileConverterSettings");
								console.error(jqHXR, textStatus, errorThrown);
							}
						});

					break;
					case 'tab_server_users':
						updateUserList(ui);
					break;
					case 'tab_server_mediaSources':
						$(ui.panel).empty();
						$(ui.panel).append("<h1>Add/Remove Media Sources</h1>");
						//list mediaSources
						$.ajax({
							cache: false,
							url: g_Toboggan_basePath+"/backend/rest.php"+"?action=retrieveMediaSourceSettings&apikey="+apikey+"&apiver="+apiversion,
							type: "GET",
							complete: function(jqxhr,status) {},
							error: function(jqxhr, status, errorThrown) {
						
								//if not logged in, display the login form
								if(jqxhr.status==401)
									doLogin();
							},
							success: function(data, status, jqxhr) {		
								//display mediaSources
								//permit update to mediaSources
								var output= $("<ul/>");
								
								for (var x=0; x<data.length; ++x)
								{
									//	data[x].mediaSourceID+" "+data[x].path+" "+data[x].displayName
									$(output).append($("<li/>").append(
										$("<input name='id' type='hidden'/>").val(data[x].mediaSourceID),
										$("<input type='text' name='path'/>").val(data[x].path),
										$("<input type='text' name='displayName'/>").val(data[x].displayName),
										$("<a href='#'>Del</a>")
											.button({
												icons: {primary: "ui-icon-circle-minus"},
												text: false
											})
											.click(function(){
												$(this).parent().remove();
												return false;
											})
									));
								}
								$(ui.panel).append(output)
									.append($("<a href='#' class='add'>New Media Source</a>")
										.button({
											icons: {primary: "ui-icon-circle-plus"},
											text: true
										})
										.click(function(){
											$("#tab_server_mediaSources ul").append(
												$("<li/>").append(
													$("<input type='text' name='path' />"),
													$("<input type='text' name='displayName' />"),
													$("<a href='#'>Del</a>")
														.button({
															icons: {primary: "ui-icon-circle-minus"},
															text: false
														}).click(function(){
															$(this).parent().remove();
															return false;
														})
												)
											);
										})
									)
									.append($("<p class='saveBar'/>").append($("<a href='#' class='save'>Save Media Sources</a>")
										.button({
											icons: {primary: "ui-icon-circle-check"},
											text: true
										})
										.click(function(event){
											event.preventDefault();
											var mediaSourceArray = [];
											//build an array of mediaSources
											$("#tab_server_mediaSources ul li").each(function(){
												var newObj = {
													'path':			$(this).children('input[name=path]').val(),
													'displayName':	$(this).children('input[name=displayName]').val()
												};
												if($(this).children('input[name=id]').length>0)
												{
													//include the id
													newObj['mediaSourceID'] = $(this).children('input[name=id]').val();
												}
												mediaSourceArray.push(newObj);
											});
											
											//console.debug(mediaSourceArray);
											
											$.ajax({
												url: g_Toboggan_basePath+"/backend/rest.php"+"?action=saveMediaSourceSettings&apikey="+apikey+"&apiver="+apiversion,
												type:'POST',
												data: {mediaSourceSettings: JSON.stringify(mediaSourceArray)},
												success: function(data, textStatus, jqXHR){
													$( "#configDialog" ).dialog( "close" );
												},
												error: function(jqHXR, textStatus, errorThrown){
													alert("A mild saving catastrophe has occurred, please check the error log");
													console.error(jqHXR, textStatus, errorThrown);
												}	
											});
										})
									));;
							},
						});	
					break;
					case 'tab_server_log_contents':
						$("#serverLogSize").change(function(){
							$.ajax({
								url: g_Toboggan_basePath+"/backend/rest.php"+"?action=getApplicationLog&apikey="+apikey+"&apiver="+apiversion,
								type:'GET',
								data: {lastNBytes: (1024*$("#serverLogSize").val())},
								success: function(data, textStatus, jqXHR){
									$("#serverLogSizeDisplay").text($("#serverLogSize").val());
									$("#server_log_contents_target").text(data.logFileText.substring(data.logFileText.indexOf('\n')+1,data.logFileText.length));
								},
								error: function(jqHXR, textStatus, errorThrown){
									alert("An error has occurred loading the server log: " + textStatus + "\n see the js error console for the full error object");
									console.error(jqHXR, textStatus, errorThrown);
								}	
							});
						});
						
						$("#serverLogSize").change();
						
						$(this).find("span.refresh").click(function(){
							$("#serverLogSize").change();
						});
						
					break;
					default:
						
				}
			}
		}).select(0);
			
		return false;
	}

	function jsonObjectToTable(jsonObject, jsonObjectSchema, classToAssign) {
		var outputTable = $("<table></table>").addClass("configTable");
		outputTable.addClass(classToAssign);
		for (var objectProperty in jsonObject) {
			objectProperty = jsonObject[objectProperty];
			var rowContent = $("<tr></tr>");
			for (var c in objectProperty)
			{
				var tableCell = $("<td></td>");
				tableCell.text(objectProperty[c]);
				
				rowContent.append(tableCell);
			}
			outputTable.append(rowContent);
		}
		return outputTable;
	}

	function prepareConverters()
	{
		if(!ajaxCache.fileTypeSettings || !ajaxCache.commandSettings || !ajaxCache.fileConverterSettings)
			return;

		var content = $("<div></div>");

		for (var x in ajaxCache.fileConverterSettings.data)
			converterSettings.converters[ajaxCache.fileConverterSettings.data[x].fileConverterId] = ajaxCache.fileConverterSettings.data[x];

		for (var y in ajaxCache.commandSettings.data)
			converterSettings.commands[ajaxCache.commandSettings.data[y].commandID] = ajaxCache.commandSettings.data[y];

		for (var z in ajaxCache.fileTypeSettings.data)
			converterSettings.fileTypes[ajaxCache.fileTypeSettings.data[z].extension] = ajaxCache.fileTypeSettings.data[z];

		var converterTable = jsonObjectToTable(converterSettings.converters, ajaxCache.fileConverterSettings.schema, "converters");
		var commandTable = jsonObjectToTable(converterSettings.commands, ajaxCache.commandSettings.schema, "commands");
		var fileTypeTable = jsonObjectToTable(converterSettings.fileTypes, ajaxCache.fileTypeSettings.schema, "filetypes");

		content.append($("<h2>File Converters</h2>"));
		content.append(converterTable);

		content.append($("<h2>Commands</h2>"));
		content.append(commandTable);

		content.append($("<h2>File Types</h2>"));
		content.append(fileTypeTable);

		$("#tab_server_streamers").empty().append(content);
	}
	
	function updateUserList(ui)
	{
		$.ajax({
			url: g_Toboggan_basePath+"/backend/rest.php"+"?action=listUsers&apikey="+apikey+"&apiver="+apiversion,
			success: function(data, textStatus, jqXHR){
				
				currentUserID = jqXHR.getResponseHeader("X-AuthenticatedUserID");
				
				$(ui.panel).empty();
				$(ui.panel).append("<h1>Add/Remove and Configure Users</h1>");
				var userList = $("<select name='userList' id='opt_user_select' />");
				
				for (var intx=0; intx<data.length; ++intx)
				{
					userList.append($("<option></option>")
										.val(data[intx].idUser)
										.text(data[intx].username)
									);
				}

				userList.change(function(){
					$("#opt_usr_rightFrameTarget").empty();
					//TODO: display loading placeholder here
					$.ajax({
						url: g_Toboggan_basePath+"/backend/rest.php"+"?action=retrieveUserSettings&apikey="+apikey+"&apiver="+apiversion,
						data: { 'userid': $(this).val() },
						success: function(data, textStatus,jqHXR){
							
							//Data driven for now!
							for (lbl in data)
							{	
								var newinputID = "opt_usr_input_"+lbl,
									newinputType = "",
									newinputDisabled = (lbl=="idUser")?true:false;		//Hacks for wierd types
							
								//if it's a type that should be numerical (bandwidth etc set the type to number
								switch(lbl)
								{
									case "maxAudioBitrate":
									case "maxVideoBitrate":
									case "maxBandwidth":
									case "trafficLimit":
									case "trafficLimitPeriod":
										newinputType = "number";													
									break;
									case "enableTrafficLimit":
									case "enabled":
										newinputType = "checkbox";													
									break;
									case "permissions":
										
										$("#permissionsTarget").remove();
										$("#opt_usr_rightFrameTarget").append(
											$("<h2 class='miniheading'>Permissions</h2>"),
											$("<div id='permissionsTarget' ></div>")
										);
										
										var tabBarContainer = $("<ul/>");
										var tabIndex=0;
										for (permissionCategory in data[lbl])
										{
											var categoryContainer = $("<div/>").attr("id","perm_tab_"+tabIndex);
											tabBarContainer.append($("<li/>")
																.append($("<a/>")
																	.attr("href","#perm_tab_"+tabIndex)
																	.text(permissionCategory)
																)
														);
											
											for (permIndex in data[lbl][permissionCategory] )
											{
												$(categoryContainer).append(
													$("<p>")
														.append($("<label />").text(data[lbl][permissionCategory][permIndex]["displayName"]))
														.append($("<input type='checkbox' />")
																	.attr('checked',data[lbl][permissionCategory][permIndex]["granted"]==="Y")
																	.attr("data-permIndex", data[lbl][permissionCategory][permIndex]["id"])
																	.attr("data-permCat", permissionCategory)
																)
												);
											}
											categoryContainer.appendTo("#permissionsTarget");
											tabIndex++;
										}
										
										$("#permissionsTarget").prepend(tabBarContainer);
										$("#permissionsTarget").tabs({selected: 0});
										
										
										continue;
									break;
									default:
										newinputType = "text";
								}
							
								$("#opt_usr_rightFrameTarget").append(
									$("<p>").append(
										$("<label>").text(lbl).attr("for", newinputID)
									).append(
										$("<input class='opt_usr_input' type='"+newinputType+"'>")
											.attr({
													"id": newinputID,
													"name": lbl,
													"value": data[lbl],
													"disabled": newinputDisabled,
													"checked": (newinputType=="checkbox" && data[lbl]=="Y")
													})
											
									)
								);
							}
							//Add the update button
							$("#opt_usr_rightFrameTarget").append(
								$("<button id='opt_usr_input_updateBtn'>Update User</button>")
									.button({
										icons: {primary: 'ui-icon-circle-check'},
										text: true
									}).click(function(e){
										e.preventDefault();
										//display indication of it!
										var btnObj = $(this);
										btnObj.text("Saving...");
										btnObj.attr("disabled",true);
										$("#opt_user_select").attr("disabled",true);
										
										var saveData = { };
										$("#opt_usr_rightFrameTarget>p>input").each(function(){
											saveData[$(this).attr("name")] = $(this).val();
											if($(this).attr("type") == "checkbox")
												saveData[$(this).attr("name")] = $(this).attr("checked")?"Y":"N";	
										});
										
										saveData.permissions = {};
										//do permissions object
										$("#permissionsTarget input[type='checkbox']").each(function(){
											if(!$.isArray(saveData.permissions[$(this).attr("data-permcat")]))
												saveData.permissions[$(this).attr("data-permcat")] = []
												
											saveData.permissions[$(this).attr("data-permcat")].push({
													id:			$(this).attr("data-permindex"),
													granted:	$(this).attr("checked")?"Y":"N"
												});
										});

										//save the user's settings
										$.ajax({
											url: g_Toboggan_basePath+"/backend/rest.php"+"?action=updateUserSettings&apikey="+apikey+"&apiver="+apiversion+"&userid="+($("#opt_usr_input_idUser").val()),
											type: "POST",
											data: {
												settings:	JSON.stringify(saveData)
											},
											success: function(data, textStatus,jqHXR){
												btnObj.text("Update");
												btnObj.attr("disabled",false);
												$("#opt_user_select").attr("disabled",false);
											},
											error: function(jqHXR, textStatus, errorThrown){
												alert("An error occurred while saving the user settings");
												console.error(jqXHR, textStatus, errorThrown);
											}
										});
									})
							).append(	//add the delete button
								$("<button id='opt_usr_input_deleteBtn'>Delete User</button>")
									.button({
										disabled: (currentUserID==$("#opt_usr_input_idUser").val()),
										icons: { primary: 'ui-icon-circle-minus'},
										text: true
									}).click(function(e){
										e.preventDefault();
										if( confirm("Delete this user?") )
										{
											var btnObj = $(this);
											btnObj.text("Deleting...");
											btnObj.attr("disabled",true);
											$("#opt_user_select").attr("disabled",true);
											
											$.ajax({
												url: g_Toboggan_basePath+"/backend/rest.php"+"?action=deleteUser&apikey="+apikey+"&apiver="+apiversion+"&userid="+($("#opt_usr_input_idUser").val()),
												type: "POST",
												success: function(data, textStatus,jqHXR){
													btnObj.text("Delete User");
													btnObj.attr("disabled",false);
													$("#opt_user_select").attr("disabled",false);
													alert("User Successfully Deleted");
													updateUserList(ui);
												},
												error: function(jqHXR, textStatus, errorThrown){
													alert("An error occurred while deleting the user");
													console.error(jqXHR, textStatus, errorThrown);
												}
											});
										}
									})
							)
							.append(	//add the fields to change the password
								$("<div id='opt_usr_input_changePasswd_container' />")
									.append(
										$("<p><label for='opt_usr_input_changePass1'>New Password</label><input type='password' id='opt_usr_input_changePass1' name='opt_usr_input_changePass1' /></p>"),
										$("<p><label for='opt_usr_input_changePass2'>Repeat</label><input type='password' id='opt_usr_input_changePass2' name='opt_usr_input_changePass2' /></p>"),
										$("<button id='opt_usr_input_changePasswd_button'>Update User's Password</button>").button({
												icons: { primary: 'ui-icon-circle-check'},
												text: true
											}).click(function(e){
												e.preventDefault();
												//check the two are the same
												
												if($("#opt_usr_input_changePass1").val() != $("#opt_usr_input_changePass2").val() && $("#opt_usr_input_changePass1").val()!="")
												{
													alert("Passwords are not equal or 0 characters");
													return;
												}
												//sha512 and then submit!
												var passwd = new jsSHA($("#opt_usr_input_changePass1").val()).getHash("SHA-256","B64");
												var btnObj = $(this);
												
												btnObj.text("Updating...");
												btnObj.attr("disabled",false);
												$("#opt_user_select").attr("disabled",false);
												
												$.ajax({
													url: g_Toboggan_basePath+"/backend/rest.php"+"?action=changeUserPassword&apikey="+apikey+"&apiver="+apiversion+"&userid="+($("#opt_usr_input_idUser").val()),
													type: "POST",
													data: {
														password:	passwd
													},
													success: function(data, textStatus,jqHXR){
														btnObj.text("Update User's Password");
														btnObj.attr("disabled",false);
														$("#opt_user_select").attr("disabled",false);
													},
													error: function(jqHXR, textStatus, errorThrown){
														alert("An error occurred while saving the user settings");
														console.error(jqXHR, textStatus, errorThrown);
													}
												});

											})

									)
							)
							
						},
						error: function(jqHXR, textStatus, errorThrown){
							alert("An error occurred while retrieving the user settings");
							console.error(jqXHR, textStatus, errorThrown);
						}
					})
				})
				
				$(ui.panel).append(
					$("<div id='opt_usr_leftFrame' />")
						.append(userList)
						.append(
							$("<a href='#'>New User</a>")
								.button({
										icons: {primary: "ui-icon-circle-plus"},
										text: true
								}).click(function(e){
									e.preventDefault();
									$("#opt_usr_rightFrameTarget").empty();
									var inputNames = new Array("username","password","email","enabled",
																	"maxAudioBitrate","maxVideoBitrate","maxBandwidth",
																	"enableTrafficLimit","trafficLimit","trafficLimitPeriod");
									var newinputType = "";
									for (x=0;x<inputNames.length;++x)
									{
										switch(inputNames[x])
										{
											case "maxAudioBitrate":
											case "maxVideoBitrate":
											case "maxBandwidth":
											case "trafficLimitPeriod":
											case "trafficLimit":
												newinputType = "number";													
											break;
											case "enableTrafficLimit":
											case "enabled":
												newinputType = "checkbox";													
											break;
											case "password":
												newinputType = "password";
											break;
											default:
												newinputType = "text";
										}
										
										newinputID = "opt_usr_input_new"+inputNames[x];
									
										$("#opt_usr_rightFrameTarget").append(
											$("<p>").append(
												$("<label>").text(inputNames[x]).attr("for", newinputID)
											).append(
												$("<input class='opt_usr_input' type='"+newinputType+"'>")
													.attr({
															"id":		newinputID,
															"name":		inputNames[x],
															"value":	'',
															})
													
											)
										);
									}
									$("#opt_usr_rightFrameTarget").append(
										$("<button id='opt_usr_input_addBtn'>Add User</button>")
											.button({
												icons: {primary: "ui-icon-circle-plus"},
												text: true
											})
											.click(function(){
												//display indication of it!
												var btnObj = $(this);
												btnObj.text("Saving...");
												btnObj.attr("disabled",true);
												$("#opt_user_select").attr("disabled",true);
												
												var saveData = {};
												$("#opt_usr_rightFrameTarget input").each(function(){
												
													saveData[$(this).attr("name")] = $(this).val();
													
													if($(this).attr("type") == "checkbox")
														saveData[$(this).attr("name")] = $(this).attr("checked")?"Y":"N";
													else if ($(this).attr("name")=="password")
													{
														//SHA256 the password
														saveData[$(this).attr("name")] = new jsSHA($(this).val()).getHash("SHA-256","B64");
													}
												});

												//save the new user
												$.ajax({
													url: g_Toboggan_basePath+"/backend/rest.php"+"?action=addUser&apikey="+apikey+"&apiver="+apiversion,
													type: "POST",
													data: {
														settings:	JSON.stringify(saveData)
													},
													success: function(data, textStatus,jqHXR){
														btnObj.text("Add");
														btnObj.attr("disabled",false);
														$("#opt_user_select").attr("disabled",false);
														updateUserList(ui);
													},
													error: function(jqHXR, textStatus, errorThrown){
														alert("An error occurred while adding the user");
														console.error(jqXHR, textStatus, errorThrown);
													}
												});
											})
									);
							})
						)
					)
					.append($("<fieldset id='opt_usr_rightFrameFieldset'><legend>User Details</legend><div id='opt_usr_rightFrameTarget'/></fieldset>"));
				//trigger the change to populate the fieldset
				userList.change();
			
			},
			error: function(jqHXR, textStatus, errorThrown){
				alert("An error occurred while retrieving the user settings");
				console.error(jqXHR, textStatus, errorThrown);
			}
		});
	}
})();
