
/**
 * Created by vlad on 03.07.2017.
 * */
$(document).ready(function(){
        var tree = document.getElementById('essaytree');
        // var tree = document.getElementById('tree');

        tree.className = 'tree';
        // console.log(tree.className);
        $('.tree').find('ul').hide();
        // $('.tree').find('ul').show();

        var treeLis = tree.getElementsByTagName('li');
        // console.log(treeLis);

        /* wrap all textNodes into spans */
        for (var i = 0; i < treeLis.length; i++) {
            var li = treeLis[i];

            var span = document.createElement('span');
            span.className = 'pl22';
            li.insertBefore(span, li.firstChild);
            span.appendChild(span.nextSibling);
        }

        /* catch clicks on whole tree */
        // tree.style.visibility = "hidden";
        tree.onclick = function(event) {

        var target = event.target;
        if (target.tagName != 'SPAN') {
            return;
        }

        /* now we know the SPAN is clicked */
        var childrenContainer = target.parentNode.getElementsByTagName('ul')[0];
        var parent = target.parentNode;
        if (!childrenContainer) {
            return;
        } // no children
        var background = $(parent).css("background-image");
        if (background == 'url("https://pk.interun.ru/pix/t/switch_plus.gif")'){
            $(parent).css("background-image","url(../../pix/t/switch_minus.gif)");
        }else {
            $(parent).css("background-image","url(../../pix/t/switch_plus.gif)");
        }
        $(childrenContainer).toggle();
    }
});


function handleDisable() {
    var chkbox1 = document.getElementsByName("isconfirm")[0]
    var chkbox2 = document.getElementsByName("ispromise")[0]
    var button = document.getElementsByName("startpagebutton")[0]
    if (chkbox1.checked == true && chkbox2.checked == true) {
        button.disabled = false
    } else {
        button.disabled = true
    }
}

function handleDisable2() {
    var chkbox = document.getElementsByName("ispromise2")[0]
    var essay = document.getElementsByName("newfile")[0];
    var button = document.getElementsByName("save")[0]
    if (chkbox.checked == true && essay.value != "") {
        button.disabled = false
    } else {
        button.disabled = true
    }
}
