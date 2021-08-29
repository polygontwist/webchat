"use strict";
var WChat=function(initdata){
	var nodeinput,nodeoutput,timer,chatdata=[];
	var myID="-";
	
	var htmlloader=function(url,refunc,errorFunc){
		var loader;
		try{
			loader = new XMLHttpRequest();
		}
		catch(e) {
			try {                        
				loader  = new ActiveXObject("Microsoft.XMLHTTP");// MS Internet Explorer (ab v6)
			} 
			catch(e){
				try {                                
						loader  = new ActiveXObject("Msxml2.XMLHTTP");// MS Internet Explorer (ab v5)
				} catch(e) {
						loader  = null;
						console.log('XMLHttp nicht möglich.');
				}
			}
		}
		var startloading=function(isreload){
			if(loader!=null){
				var u=url;
				if(isreload){
					u+='&reload=true';
				}
				loader.open('GET',u,true);//open(method, url, async, user, password)
				loader.responseType='text'; //!                
				loader.setRequestHeader('Content-Type', 'text/plain'); 
				loader.setRequestHeader('Cache-Control', 'no-cache'); 
				loader.setRequestHeader('Access-Control-Allow-Headers', '*');
				loader.setRequestHeader('Access-Control-Allow-Origin', '*');
				loader.onreadystatechange = function(e){                
					if (this.readyState == 4) {
						if(loader.status!=200){}
					}
				};
				loader.onload=function(e){
					if(typeof refunc==="function")refunc(this.responseText);
				}				
				loader.onabort = loader.onerror = function(e){
					if(typeof errorFunc==="function")errorFunc(e);
				}
				// loader.timeout=  //ms
				loader.send(null);
 
			}
		}
		//--API--
		this.reload=function(){
			startloading(true);
		}
 
		startloading(false);
	}

	var parseJSON=function(s){
		var re={};
		if(s=="undefined")s="{}";
		if(s==undefined)s="{}";
		if(s==null)s="{}";
		s=s.split("\n").join('').split("\r").join('').split("\t").join('');	
		//s=s.split("'").join('"');	//not: kann im Titel enthalten sein.
		try{
			re=JSON.parse(s);
		}
		catch(err) {
			console.log("JSONfehler",err.message,{"s":s});
			re={};
		}
		return re;
	}

	var onloadchat=function(data){
		var ac=parseJSON(data);
		//console.log(ac);
		if(ac["actiontome"]!=""){
			console.log("sc to me",ac["actiontome"]);
		}
		
		if(ac["clientID"]!=""){
			myID=ac["clientID"];
		}
		
		if(ac["chat"]!=undefined){
			showchat(ac["clienttime"],ac["chat"],ac["clients"]);
		}
		
		if(timer!=undefined)clearTimeout(timer);
		timer=setTimeout(headbeat,3000);//1000=1s sec
		
		nodeinput.className="";
	}
	
	
	var getClientnummer=function(liste,cid){
		var re="",i,c;
		for(i=0;i<liste.length;i++){
			c=liste[i].split("|");
			if(c[0]==cid)re=i+1;
		}
		if(re=="")re=cid;
		return re;
	}
	
	var showchat=function(ti,chatlist,clients){
		if(nodeinput==undefined)return;
		if(chatlist.length==0)return;
		var i,t,c,carr,co,last,
			pos=0,
			erster=chatlist[0].split('|');
		
		//neue von chatlist übernehmen
		
		//position der neuen daten suchen
		if(chatdata.length>0){
			last=chatdata[chatdata.length-1];
			for(i=0;i<chatlist.length;i++){
				carr=chatlist[i].split('|');//id|time|text
				if(last["ti"]==carr[1]){
					pos=i+1;
				}
			}
		}
		
		//neue Daten übrnehmen	und anzeigen	
		
		for(i=pos;i<chatlist.length;i++){
			carr=chatlist[i].split('|');//id|time|text
			//chatdata.push({"ti":carr[1]});			
			chatdata=[{"ti":carr[1]}];//nur letzten merken, history kann weg			
			
			if(myID==carr[0]){
				//outputstr('ich: '+carr[2],true);
				outputstr('User'+getClientnummer(clients,carr[0])+' '+carr[2],true);
				var sp=document.getElementById("me");
				if(sp!=undefined)sp.innerHTML='User'+getClientnummer(clients,carr[0]);
				
			}
			else
				outputstr('User '+getClientnummer(clients,carr[0])+'>: '+carr[2],false);
		}
		
	}
	
	var sendstr=function(s){
		var lo;
		//outputstr(s);
		if(s==="host clear")
			lo=new htmlloader("chat.php?action=host|clear",onloadchat);
		else
			lo=new htmlloader("chat.php?msg="+encodeURIComponent(s),onloadchat);
	}
	
	var outputstr=function(s,isme){
		if(nodeoutput!=undefined){
			var newNode=document.createElement("p");
			newNode.innerHTML=s;
			if(isme)newNode.className="me";
			nodeoutput.appendChild(newNode);
			nodeoutput.scrollTo({
					  top: nodeoutput.scrollHeight,
					  left: 0,
					  behavior: 'smooth'
					});
		}
	}
	
	var inkeydown=function(e){
		if (e.key === 'Enter') {
		 var instr=nodeinput.value;
		 nodeinput.value="";
		if(instr!="")
			nodeinput.className="send";
			sendstr(instr);
		}
	}
	
	var headbeat=function(){
		if(timer!=undefined)clearTimeout(timer);
		var lo=new htmlloader("chat.php",onloadchat);
	}
	
	var onloadfirst=function(data){
		var ac=parseJSON(data);console.log(ac);
		if(ac["clientID"]!=""){
			myID=ac["clientID"];
			var liste=ac["clients"];
			var mynr=getClientnummer(ac["clients"],myID);
			
			sendstr("[ist da]");
		}
	}
	
	
	var init=function(){
		if(initdata["in"]!=undefined){
			nodeinput=document.getElementById(initdata["in"]);
			if(nodeinput!=undefined){
				nodeinput.addEventListener('keypress',inkeydown);
			}
		}
		if(initdata["out"]!=undefined){
			nodeoutput=document.getElementById(initdata["out"]);
			nodeoutput.value="ready.";
		}
		
		var lo=new htmlloader("chat.php",onloadfirst);
		headbeat();
		
	}
	
	
	init();
}

export { WChat };
