;(function (window, $, undefined) {
  'use strict';

  var domParts,
      queryParams = {};

  $(function () {  //Entry Point
    domParts = {
      newUserSuccessModal: $("#newUserSuccessModal"),
      newUserExistsErrorModal: $("#newUserExistsErrorModal"),
      existingUserSuccessModal: $("#existingUserSuccessModal"),
      existingUserErrorModal: $("#existingUserErrorModal"),
      generalErrorModal: $("#generalErrorModal"),

      nameInput: $("#nameInput"),
      websiteURLInput: $("#websiteURLInput"),
      emailInput: $("#emailInput"),
      passwordInput: $("#passwordInput"),
      verifyPasswordInput: $("#verifyPasswordInput"),
      submitRegister: $("#submitRegister"),

      siteIdInput: $("#siteIdInput"),
      submitSiteId: $("#submitSiteId"),

      registerFormBox: $("#newUsersBox"),
      existingFormBox: $("#existingUsersBox")
    };

    queryParams['tagId'] = $('#errnioSettingsAdmin').attr('data-tagId');
    queryParams['installName'] = $('#errnioSettingsAdmin').attr('data-installName');

    // Hook GO! buttons to submit forms to submit corresponding forms
    domParts.submitRegister.on('click', onSendRegisterForm);
    domParts.submitSiteId.on('click', onSendSiteIdForm);

    // Hook Enter Key when using input fields to submit corresponding forms
    $('#existingUsersBox').find('.errnio-form-box-fields > input').on('keydown', function(ev){
      var keyCcode = ev.keyCode || ev.which;
      if (keyCcode == 13) { //Enter keycode
        onSendRegisterForm(ev);
      }
    });

    $('#newUsersBox').find('.errnio-form-box-fields > input').on('keydown', function(ev){
      var keyCcode = ev.keyCode || ev.which;
      if (keyCcode == 13) { //Enter keycode
        onSendRegisterForm(ev);
      }
    });

    // Validate password length
    domParts.passwordInput.on('blur', function(ev){
      var password = domParts.passwordInput.val();
      if (!isValidPassword(password)) {
        $('.inputMsg.password').text('Password should be at least 7 characters');
      }
      else {
        $('.inputMsg.password').text('');
      }
    });

    // General modal behaviour - close for any tap/click outside elements button or input
    $(".errnio-modal").on('click', onModalClick);


    // Set active state toggling between the two boxes
    domParts.registerFormBox.on('click', function(ev) {
      domParts.existingFormBox.removeClass("errnio-active");
      domParts.registerFormBox.addClass("errnio-active");
    });
    domParts.existingFormBox.on('click', function(ev) {
      domParts.registerFormBox.removeClass("errnio-active");
      domParts.existingFormBox.addClass("errnio-active");
    });

    // Set Register Box as active
    domParts.registerFormBox.addClass("errnio-active");

    //Auto focus first input
    domParts.nameInput.focus();
  });

  function onSendRegisterForm(ev){
    $('.inputMsg').text('');  //Clear previous messages

    var inputData = getInputDataAndValidate();

    if(!inputData) {
      return;
    }

    inputData.registrationSource = 'wordpress';
    inputData.installerName = getDataFromUrlParams('installName');
    inputData.tagId = getDataFromUrlParams('tagId');

    submitRegisterForm(inputData, 'register');

    ev.preventDefault();
    return false;
  }

  function getInputDataAndValidate() {
    //Validate name
    var name = domParts.nameInput.val();
    if (!name) {
      $('.inputMsg.name').text('Please insert your name');
      return false;
    }

    //Validate URL
    var url = domParts.websiteURLInput.val();
    if (!url.match(/^[a-zA-Z]+:\/\//)) {  //Add protocol
      url = 'http://' + url;
    }
    if (!isValidURL(url)) {
      $('.inputMsg.site').text('Please use a valid website address');
      return false;
    }

    //Validate email
    var email = domParts.emailInput.val();
    if (!isValidEmail(email)) {
      $('.inputMsg.email').text('Please insert a valid email address');
      return false;
    }

    //Validate password
    var password = domParts.passwordInput.val();
    if (!isValidPassword(password)) {
      $('.inputMsg.password').text('Password should be at least 7 characters');
      return false;
    }

    //Validate verify password
    var verifyPassword = domParts.verifyPasswordInput.val();
    if (!isValidVerifyPassword(verifyPassword)) {
      $('.inputMsg.verifyPassword').text('Passwords don\'t match');
      return false;
    }

    return {
      name: name,
      domain: url,
      email: email,
      password: password,
      verifyPassword: verifyPassword
    };
  }

  function getDataFromUrlParams(paramName) {
    return queryParams[paramName] || '';
  }

  function onSendSiteIdForm(ev){
    $('.inputMsg').text('');  //Clear previous messages

    //Validate name
    var newTagId = domParts.siteIdInput.val();
    if (!newTagId) {
      $('.inputMsg.siteid').text('Please enter site id');
      return 0;
    } else if (!isValidErrnioId(newTagId)){
      toggleModal(domParts.existingUserErrorModal, true);
      return 0;
    }

    var oldTagId = getDataFromUrlParams('tagId');

    submitRegisterForm({ newTagId: newTagId, oldTagId: oldTagId }, 'switchTag');

    ev.preventDefault();
    return false;
  }

  function submitRegisterForm(data, type) {
    onSubmitSuccess(type);

    $.ajax({
      url: getPostUrl(type),
      type: "POST",
      data : JSON.stringify(data),
      dataType: "json",
      contentType: "application/json",
      crossDomain: true,
      cache: false,
      success: function (response, status) {
        if(response && response.success == true && status === 'success') {
          postMessageToWP({ type: type, tagId: (type === 'register') ? data.tagId : data.newTagId });
        }
        else {
          onSubmitError({ type: type, formData: data, message: response.message, status: status });
        }
      },
      error: function (xhr, status, error) {
        onSubmitError({ type: type, requestData: data, statusCode: status, responseText: error });
      }
    });
  }

  function onSubmitError(errorData) {
    // Choose fail modal
    var failModal = domParts.generalErrorModal;

    if(errorData.message.toLowerCase() === 'user already exists with this email') {
      failModal = domParts.newUserExistsErrorModal;
    }

    $(".errnio-modal").on('click', onModalClick);

    toggleModal(domParts.newUserSuccessModal, false);
    toggleModal(domParts.existingUserSuccessModal, false);
    toggleModal(failModal, true);
  }

  function onSubmitSuccess(type) {
    $(".errnio-modal").off('click', onModalClick);

    if (type == 'register') {
      toggleModal(domParts.newUserSuccessModal, true);
    } else {
      toggleModal(domParts.existingUserSuccessModal, true);
    }
  }

  function postMessageToWP (data) {
    $.ajax({
      url : errniowp.ajax_url,
      type : 'post',
      data : {
        'action': 'nextarticle_by_errnio_register',
        'tag_id': data.tagId,
        'type': data.type
      },
      success : function( response ) {
      }
    });
  }

  function onModalClick(e) {
    toggleModal($(this), false);
  }

  function toggleModal($el, shouldShow) {
    if (shouldShow) {
      $el.addClass('show');
    } else {
      $el.removeClass('show');
    }
  }

  function isValidEmail(email) {
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,16})+$/;
    return regex.test(email);
  }

  function isValidPassword(password) {
    return (password.length >= 7)
  }

  function isValidVerifyPassword(verifyPassword) {
    var password = domParts.passwordInput.val();
    return (verifyPassword === password);
  }

  function isValidURL(url) {
    var expression = /[-a-zA-Z0-9@:%_\+.~#?&//=]{2,256}\.[a-z]{2,16}\b(\/[-a-zA-Z0-9@:%_\+.~#?&//=]*)?/gi;
    var regex = new RegExp(expression);
    return !!url.match(regex);
  }

  function isValidErrnioId(errnioId) {
    var hexRegex = /^[0-9A-F]+$/i;
    return (hexRegex.test(errnioId) && errnioId.length === 24);
  }

  function getPostUrl(type) {
    return 'https://customer.errnio.com/' + type;
  }

}(window, window.jQuery));
