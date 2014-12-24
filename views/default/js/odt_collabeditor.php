<?php

# Using data-document-url on the editor div to get the url from where to download the document
# TODO: is there another/better way to pass data from views to javascript methods?

?>
//<script>

elgg.provide("elgg.odt_collabeditor");

/*jslint unparam: true*/
/*global runtime, core, ops, typistGlobals, location, io*/
var TypistOperationRouter = function (socket, odfContainer, errorCb) {
    "use strict";
    runtime.loadClass("core.EventNotifier");
    runtime.loadClass("core.Task");
    runtime.loadClass("ops.OperationTransformer");

    var EVENT_BEFORESAVETOFILE = "beforeSaveToFile",
        EVENT_SAVEDTOFILE = "savedToFile",
        EVENT_HASLOCALUNSYNCEDOPERATIONSCHANGED = "hasLocalUnsyncedOperationsChanged",
        EVENT_HASSESSIONHOSTCONNECTIONCHANGED =   "hasSessionHostConnectionChanged",
        EVENT_MEMBERADDED = "memberAdded",
        EVENT_MEMBERCHANGED = "memberChanged",
        EVENT_MEMBERREMOVED = "memberRemoved",

        operationFactory,
        playback,
        lastServerSeq = 0,
        syncLock = false,
        commitTask,
        hasLocalUnsyncedOps = false,
        hasSessionHostConnection = true,
        unplayedServerOpSpecQueue = [],
        unsyncedClientOpSpecQueue = [],
        eventNotifier = new core.EventNotifier([
            EVENT_BEFORESAVETOFILE,
            EVENT_SAVEDTOFILE,
            EVENT_HASLOCALUNSYNCEDOPERATIONSCHANGED,
            EVENT_HASSESSIONHOSTCONNECTIONCHANGED,
            EVENT_MEMBERADDED,
            EVENT_MEMBERCHANGED,
            EVENT_MEMBERREMOVED,
            ops.OperationRouter.signalProcessingBatchStart,
            ops.OperationRouter.signalProcessingBatchEnd
        ]),
        operationTransformer = new ops.OperationTransformer();

    function handleConflict(serverOps) {
        var i,
            transformResult = operationTransformer.transform(unsyncedClientOpSpecQueue, serverOps);

        if (!transformResult) {
            errorCb("Has unresolvable conflict: ");
            return false;
        }

        unsyncedClientOpSpecQueue = transformResult.opSpecsA;
        unplayedServerOpSpecQueue = unplayedServerOpSpecQueue.concat(transformResult.opSpecsB);

        return true;
    }

    function playbackOps() {
        var op, i;

        eventNotifier.emit(ops.OperationRouter.signalProcessingBatchStart, {});
        for (i = 0; i < unplayedServerOpSpecQueue.length; i += 1) {
            op = operationFactory.create(unplayedServerOpSpecQueue[i]);
            if (op !== null) {
                if (!playback(op)) {
                    eventNotifier.emit(ops.OperationRouter.signalProcessingBatchEnd, {});
                    errorCb("opExecutionFailure");
                    return;
                }
            } else {
                eventNotifier.emit(ops.OperationRouter.signalProcessingBatchEnd, {});
                errorCb("Ignoring invlaid incoming opspec: " + op);
                return;
            }
        }
        unplayedServerOpSpecQueue.length = 0;
        eventNotifier.emit(ops.OperationRouter.signalProcessingBatchEnd, {});
    }

    function receiveOps(head, operations) {
        if (head !== lastServerSeq && unsyncedClientOpSpecQueue.length > 0) {
            handleConflict(operations);
        } else {
            unplayedServerOpSpecQueue = unplayedServerOpSpecQueue.concat(operations);
        }
        lastServerSeq = head;
    }

    function commitOps() {
        var originalUnsyncedLength = unsyncedClientOpSpecQueue.length;
        if (originalUnsyncedLength) {
            syncLock = true;

            socket.emit("commit_ops", {
                head: lastServerSeq,
                ops: unsyncedClientOpSpecQueue
            }, function (response) {
                if (response.conflict === true) {
                    receiveOps(response.head, response.ops);
                    commitOps();
                } else {
                    lastServerSeq = response.head;
                    if (unsyncedClientOpSpecQueue.length > originalUnsyncedLength) {
                        unsyncedClientOpSpecQueue.splice(0, originalUnsyncedLength);
                        commitOps();
                    } else {
                        unsyncedClientOpSpecQueue.length = 0;
                        playbackOps();
                        syncLock = false;
                    }
                }
            });
        }
    }

    this.setOperationFactory = function (f) {
        operationFactory = f;
    };

    this.setPlaybackFunction = function (f) {
        playback = f;
    };

    this.push = function (operations) {
        var i, op, opspec;

        eventNotifier.emit(ops.OperationRouter.signalProcessingBatchStart, {});

        for (i = 0; i < operations.length; i += 1) {
            opspec = operations[i].spec();

            opspec.timestamp = Date.now();
            op = operationFactory.create(opspec);

            if (!playback(op)) {
                errorCb("opExecutionFailure");
                return;
            }

            unsyncedClientOpSpecQueue.push(opspec);
        }

        commitTask.trigger();

        eventNotifier.emit(ops.OperationRouter.signalProcessingBatchEnd, {});
    };

    this.requestReplay = function (cb) {
        var cbOnce = function () {
            eventNotifier.unsubscribe(ops.OperationRouter.signalProcessingBatchEnd, cbOnce);
            cb();
        };
        eventNotifier.subscribe(ops.OperationRouter.signalProcessingBatchEnd, cbOnce);
        socket.emit("replay", {});
    };

    this.close = function (cb) {
        cb();
    };

    this.subscribe = function (eventId, cb) {
        eventNotifier.subscribe(eventId, cb);
    };

    this.unsubscribe = function (eventId, cb) {
        eventNotifier.unsubscribe(eventId, cb);
    };

    this.hasLocalUnsyncedOps = function () {
        return hasLocalUnsyncedOps;
    };

    this.hasSessionHostConnection = function () {
        return hasSessionHostConnection;
    };

    function init() {
        commitTask = core.Task.createTimeoutTask(function () {
            if (!syncLock) {
                commitOps();
            }
        }, 300);

        var replayed = false;
        socket.on("replay", function (data) {
            receiveOps(data.head, data.ops);
            playbackOps();
            replayed = true;
        });

        socket.on("new_ops", function (data) {
            if (replayed && !syncLock) {
                syncLock = true;
                receiveOps(data.head, data.ops);
                playbackOps();
                syncLock = false;
            }
        });
    }
    init();
};

var ClientAdaptor = function (connectedCb, kickedCb, disconnectedCb, sessionId, genesisPath, token) {
    "use strict";
    var memberId,
        socket;

    this.getMemberId = function () {
        return memberId;
    };

    this.getGenesisUrl = function () {
        return "https://127.0.0.1:3000" + genesisPath;
    };

    this.createOperationRouter = function (odfContainer, errorCb) {
        runtime.assert(Boolean(memberId), "You must be connected to a session before creating an operation router");
        return new TypistOperationRouter(socket, odfContainer, errorCb);
    };

    this.joinSession = function (cb) {
        socket.on("join_success", function handleJoinSuccess(data) {
            socket.removeListener("join_success", handleJoinSuccess);
            memberId = data.memberId;
            cb(memberId);
        });
        socket.emit("join", {
            sessionId: sessionId
        });
    };

    this.leaveSession = function (cb) {
        socket.emit("leave", {}, cb);
    };

    this.getSocket = function () {
        return socket;
    };

    function init() {
        socket = io.connect("https://127.0.0.1:3000", {
            query: 'token=' + token
        }); // TODO: get this from some var
        socket.on("connect", connectedCb);
        socket.on("kick", kickedCb);
        socket.on("disconnect", disconnectedCb);
    }
    init();
};

elgg.odt_collabeditor.init = function() {
    var editor,
        sessionId,
        canSave,
        clientAdaptor;

    /*jslint emptyblock: true*/
    function onEditingStarted() {
    }
    /*jslint emptyblock: false*/

    function closeEditing() {
        editor.leaveSession(function () {
            clientAdaptor.leaveSession(function () {
                console.log("Closed editing, left session.");
            });
        });
    }

    function handleEditingError(error) {
        elgg.register_error(error);
        console.log(error);
        closeEditing();
    }

    function save() {
        editor.getDocumentAsByteArray(function(err, data) {
            if (err) {
                elgg.register_error(err);
                return;
            }

            // TODO: get original filename here, if needed, by name: parameter
            var blob = new Blob([data.buffer], {type: "application/vnd.oasis.opendocument.text"});
            var formData = new FormData();

            formData.append("upload", blob);
            formData.append("file_guid", sessionId);
            var token = {};
            elgg.security.addToken(token);
            Object.keys(token).forEach(function (k) {
                formData.append(k, token[k]);
            });

            elgg.post("action/odt_editor/upload", {
                data: formData,
                contentType: false, // not "multipart/form-data", false lets browser do the right thing, ensures proper encoding of boundaryline
                processData: false,
                error: function() {
                    elgg.system_message('Save failed!');
                },
                success: function(data) {
                    data = runtime.fromJson(data);
//                     elgg.system_message('Save result: '+data.status);
                    if (data.system_messages.error.length > 0) {
                        elgg.system_message('Save error: '+data.system_messages.error[0]);
                    }
                    if (data.system_messages.success.length > 0) {
                        elgg.system_message('Save success: '+data.system_messages.success[0]);
                    }
                }
            });
        });
    }

    function deleteSession() {
        // TODO: warn about unsaved data
        var formData = new FormData();

        formData.append("file_guid", sessionId);

        var token = {};
        elgg.security.addToken(token);
        Object.keys(token).forEach(function (k) {
            formData.append(k, token[k]);
        });

        elgg.post("action/odt_collabeditor/delete_session", {
            data: formData,
            contentType: false, // not "multipart/form-data", false lets browser do the right thing, ensures proper encoding of boundaryline
            processData: false,
            error: function() {
                elgg.system_message('Deleting the session failed!');
            },
            success: function(data) {
                data = runtime.fromJson(data);
                if (data.system_messages.error.length > 0) {
                    elgg.system_message('Session deletion error: '+data.system_messages.error[0]);
                }
                if (data.system_messages.success.length > 0) {
                    elgg.system_message('Session deletion success: '+data.system_messages.success[0]);
                    // TODO: where should this forward to? file manager list? which url?
                    elgg.forward();
                }
            }
        });
    }

    function onConnect() {
        console.log("onConnect.");
        clientAdaptor.joinSession(function (memberId) {
            if (!memberId) {
                console.log("Could not join; memberId not received");
            } else {
                console.log("Joined with memberId " + memberId);

                var editorConfig = {
                    allFeaturesEnabled: true,
                    saveCallback: canSave ? save : undefined,
                    closeCallback: canSave ? deleteSession : undefined
                };

                Wodo.createCollabTextEditor("odt_collabeditor", editorConfig, function (err, e) {
                 console.log("createCollabTextEditor");
                   if (err) {
                        console.log(err);
                        return;
                    }
                    editor = e;
                    editor.addEventListener(Wodo.EVENT_UNKNOWNERROR, handleEditingError);
                    editor.joinSession(clientAdaptor, onEditingStarted);
                });
            }
        });
    }
    function onKick() {
        console.log("onKick.");
        closeEditing();
    }
    function onDisconnect() {
        console.log("onDisconnect.");
    }

    var odtCollabEditorDiv = document.getElementById("odt_collabeditor");
    sessionId = odtCollabEditorDiv && odtCollabEditorDiv.getAttribute("data-session-id");
    var genesisDocPath = odtCollabEditorDiv && odtCollabEditorDiv.getAttribute("data-genesisdoc-path");
    var token = odtCollabEditorDiv && odtCollabEditorDiv.getAttribute("data-token");
    canSave = odtCollabEditorDiv && odtCollabEditorDiv.getAttribute("data-can-save");

    clientAdaptor = new ClientAdaptor(onConnect, onKick, onDisconnect, sessionId, genesisDocPath, token);
}

//register init hook
elgg.register_hook_handler("init", "system", elgg.odt_collabeditor.init);
