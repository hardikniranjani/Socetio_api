require("dotenv").config();
const port = process.env.SOCKET_PORT;
const main_server_url = process.env.SERVER_URL;
var express = require("express");
var app = express();
var server = app.listen(port ,()=>{console.log( `listen to port ${port}`)});
var io = require("socket.io").listen(server);
var axios = require("axios");

users = [];
connections = [];
//var people={};
app.use('/', (req,res)=>{
  res.send('hello world');
})
console.log("Server connected done");

io.sockets.on("connection", function (socket) {
  //var server_url = "http://165.232.189.85/fade_backend/public/api/mobile/";

  var server_url = main_server_url;

  console.log(server_url);
  connections.push(socket);
  console.log("Connected : total connections are " + connections.length);

  socket.on("getInbox", async function (data) {
    //console.log("data.token");
    //people[data.login_user_id] =  socket.id;
    //console.log(people);
    const url = server_url + "getInbox";
    const config = {
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + data.token,
      },
    };
    //console.log(data);
    let result = await axios.post(url, data, config);

    //console.log(result.data);
    if (parseInt(result.data.success) == 1) {
      let data1 = {
        admin: result.data.admin,
        barbers: result.data.barbers,
        users: result.data.users,
        token: data.token,
      };
      io.sockets.emit("setInbox", data1);
    }
  });

  socket.on("getMessages", async function (data) {
    const url = server_url + "getMessages";
    const config = {
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + data.token,
      },
    };
    let result = await axios.post(url, data, config);
    if (parseInt(result.data.success) == 1) {
      result.data.token = data.token;
      io.sockets.emit("setMessages", result.data);
    }
  });

  socket.on("sendMessage", async function (data) {
    //console.log(data);
    const url = server_url + "sendMessage";
    const config = {
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + data.token,
      },
    };
    //console.log(data);
    const result1 = await axios.post(url, data, config);
    if (parseInt(result1.data.success) == 1) {
      result1.data.token = data.token;
      io.sockets.emit("getCurrentMessage", result1.data);
    }
  });

  socket.on("sendFile", async function (data) {
    const url = server_url + "sendFile";
    const config = {
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + data.token,
      },
    };
    //console.log(data);
    const result1 = await axios.post(url, data, config);
    //console.log(result1.data);
    if (parseInt(result1.data.success) == 1) {
      result1.data.token = data.token;
      io.sockets.emit("getCurrentMessage", result1.data);
    }
  });

  socket.on("setReadMessage1", async function (data) {
    const url = server_url + "setReadMessage1";
    const config = {
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + data.token,
      },
    };
    const read = await axios.post(url, data, config);
  });

  socket.on("deleteMessage", async function (data) {
    console.log(data);
    const url = server_url + "deleteMessage";
    const config = {
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + data.token,
      },
    };
    const deleteData = await axios.post(url, data, config);
    console.log(deleteData);
    if (parseInt(deleteData.data.success) == 1) {
      deleteData.data.token = data.token;
      io.sockets.emit("getCurrentMessage", deleteData.data);
    }
  });

  socket.on("typing", function (data) {
    // Typing
    io.sockets.emit("typing", data);
  });
  socket.on("stop typing", function (data) {
    // stop typing
    io.sockets.emit("stop typing", data);
  });
  socket.on("disconnect", function (data) {
    // Disconnect
    //console.log(data);
    connections.splice(connections.indexOf(socket), 1);
    console.log("Disconnected  : total connections are " + connections.length);
  });

});
