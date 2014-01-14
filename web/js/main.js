;
(function ($) {
    $(document).ready(function () {
        $("#spinner").hide();
        $("#web2copy, #hiw").on("click", function () {
            $("body").scrollTo($(".features"), 800);

        });
        $("#tokensubmit").on("click", function () {
            $(".urlerror").hide();

            var token = $("#token").val();
            var url = $("#url").val();
            var filename = $("#filename").val();
            if (url) {
                $("#spinner").show();

                $.post("/api/token", {token: token, url: url, filename: filename}, function (data) {
                    if (data.error == 0) {
                        $("#url").val("");
                        $("#token").val("");
                        $("#spinner").hide();
                        $("#myModal").modal("hide");

                        $("#messagetitte").html("Successful");
                        $("#messagebody").html("Your request to transfer file was successful. We will send a notification to the token owner once the transfer is complete");
                        $("#message").modal("show");
                    } else {

                        $("#spinner").hide();
                        $("#myModal").modal("hide");

                        $("#messagetitte").html("Error!");
                        $("#messagebody").html('<div class="alert alert-danger">' + data.message + '</div>');
                        $("#message").modal("show");
                    }
                }, "json");
            }else{
                $(".urlerror").show();
            }
        })
    })
})(jQuery);