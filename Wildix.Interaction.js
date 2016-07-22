window.Wildix = window.Wildix || {};


Wildix.Interaction = (function () {

	var apiVersion = 1;
	var COLLABORATION_WEB_APP_API = "COLLABORATION_WEB_APP";

	var pbxVersion = null;
    var initialized = false;

	var _requestsCallback = {};
	var _eventCallback = {};

	function _generateID(len) {
		if(!len){
			len = 32;
		}
		var text = "";
	    var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

	    for(var i=0; i < len; i++)
	        text += possible.charAt(Math.floor(Math.random() * possible.length));

	    return text;
	}

	/**
     * Process messages received from collaboration by executing callbacks, if any.
     * The event object contains the following fields:
     *      method: the API method that was called.
     *      result: result returned from the call.
     *      error: an error message if any errors were encountered.
     */
    function processPostMessage(event) {

        try {
            var message = null;
			if(event.data && event.data != ''){
				try{
					message = JSON.parse(event.data);
				}catch(e){
					console.log(e);
				}
			}

			if(message && message.hasOwnProperty('message')){
				switch (message.message.substr(0, 2)) {
					case 'E_': message.type = 'event';
						break;
					case 'R_': message.type = 'response';
						break;
					default: message.type = 'message';
						break;
				}
				var app = message.message.substr(2);
				if(app != COLLABORATION_WEB_APP_API){
					return;
				}
			}
        } catch (e) {
            console.log("Failed to process API response: ", e);
        }

		if(message && message.hasOwnProperty('id')){
			if(message.type == 'response' && _requestsCallback.hasOwnProperty(message.id)){
				if(_requestsCallback[message.id].hasOwnProperty('callback')){
					_requestsCallback[message.id]['callback'](message.msgdata);
				}
				delete _requestsCallback[message.id];
			}else if(message.type == 'event' && _eventCallback.hasOwnProperty(message.msgdata.event)){
				var eventCallbacks = _eventCallback[message.msgdata.event];
				if(eventCallbacks.length > 0){
					for(var i=0; i < eventCallbacks.length;i++){
						eventCallbacks[i](message.msgdata.msgdata);
					}
				}
			}
		}
    }

    function send(message, callback, timeout) {
    	if(!callback || !message){
    		return;
    	}

		if(!message.hasOwnProperty('id')){
			message.id = _generateID();
		}

		_requestsCallback[message.id] = {
				callback: callback,
				message: message
		};

		message.version = apiVersion;
		message.message = "M_"+COLLABORATION_WEB_APP_API;

		window.parent.postMessage(JSON.stringify(message), '*');
	}

	return {
		/*
		 * Initializes API to listen for responses from collaboration.
         */
        initialize: function () {
        	if(window.parent){
        		// attach postMessage event to handler
                if (window.attachEvent) {
                    window.attachEvent('onmessage', processPostMessage);
                } else {
                    window.addEventListener('message', processPostMessage, false);
                }
                this.getVersion(function(response){
                	if(response && response.type && response.type == 'result'){
                		pbxVersion = response.result;
                		initialized = true;
                	}
                });
        	}
        },
        isInitialized: function(callback){
        	callback({
        		type: 'result',
        		result: initialized
        	})
        },
        getVersion: function(callback){
        	send({
				'msgdata' :  {
					'command' : 'getVersion'
				}
        	}, callback);
        },

        getCalls: function(callback){
        	send({
				'msgdata' :  {
					'command' : 'getCalls'
				}
        	}, callback);
        },
        call: function(number, callback){
        	send({
				'msgdata' :  {
					'command' : 'call',
					'msgdata': {
						'number': number
					}
				}
        	}, callback);
        },
        on: function(event, callback){
        	if(_eventCallback.hasOwnProperty(event) && _eventCallback[event].length > 0){
        		_eventCallback[event].push(callback);
        		return;
        	}

        	send({
				'msgdata' :  {
					'command' : 'onEvent',
					'msgdata': {
						'event': event
					}
				}
        	}, function(result){
        		//console.log('response onEvent', result)
        		if(!_eventCallback.hasOwnProperty(event)){
        			_eventCallback[event] = [];
        		}
        		_eventCallback[event].push(callback);
        	});
        },
        off: function(event, callback){
        	if(!callback){
        		if(_eventCallback.hasOwnProperty(event)){
            		delete _eventCallback[event];
            	}
        	}else{
        		if(_eventCallback.hasOwnProperty(event)){
        			var eventCallbacks = _eventCallback[event];
    				if(eventCallbacks.length > 0){
    					for(var i=0; i < eventCallbacks.length;i++){
    						if(eventCallbacks[i] === callback){
    							eventCallbacks.splice(i, 1);
    						}
    					}
    				}

    				if(eventCallbacks.length == 0){
    					delete _eventCallback[event];
    				}
        		}
        	}


        	if(!_eventCallback.hasOwnProperty(event)){
        		send({
    				'msgdata' :  {
    					'command' : 'offEvent',
    					'msgdata': {
    						'event': event
    					}
    				}
            	}, function(result){
            		console.log('response offEvent', result)
            	});
        	}
        }
	}
})();

Wildix.Interaction.initialize();