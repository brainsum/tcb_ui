(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.chatbot = {
    attach: function (context) {
      let submit_button = $('#tcb-ui-user-input-submit');
      let conversation_field = $('#conversation');

      let scrollDown = function () {
        conversation_field.scrollTop(conversation_field.prop("scrollHeight") - conversation_field.prop("clientHeight"));
      };

      function createUserResponse(message) {
        let response = '<div class="chat-message-user clearfix">';
        response += '<div class="chat-message-content category clearfix">';
        response += '<h5>' + Drupal.t('You') + '</h5><p>';
        response += message;
        response += '</p></div></div>';
        return response;
      }

      $(document).once().ajaxStop(scrollDown());
      $('#chatblock').once().change(scrollDown());

      submit_button.once().click(function(event){
        event.stopPropagation();
        event.preventDefault();

        let user_input_field = $('#user-input');
        let user_input = user_input_field.val();
        // let hidden_user_input_field = $('#hidden-user-input');

        conversation_field.append(createUserResponse(user_input));
        // hidden_user_input_field.val(user_input);
        user_input_field.val("");
        scrollDown();
      });
    }
  };

})(jQuery, Drupal);
