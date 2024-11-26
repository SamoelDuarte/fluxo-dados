/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "/";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 1);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/admin/main.js":
/*!************************************!*\
  !*** ./resources/js/admin/main.js ***!
  \************************************/
/*! no static exports found */
/***/ (function(module, exports) {

(function ($) {
  "use strict";

  $(document).on('click', '.delete', function () {
    var id = $(this).attr('data-id');
    $('#id').val(id);
  }); // Jquery Mask

  $('.cpf').mask('000.000.000-00', {
    reverse: true
  });
  $('.money').mask("#.##0,00", {
    reverse: true
  });
  $('.mes_ano').mask('00/0000');
  $('.numero').mask('0#');
  $('.cm').mask('##0.00', {
    reverse: true
  });
  $('.kg').mask('##0.000', {
    reverse: true
  });
  $('.summernote').summernote({
    lang: 'pt-BR',
    height: 200,
    fontNames: ['Noto Sans JP', 'Signika', 'Open Sans', 'Arial'],
    fontNamesIgnoreCheck: ['Nunito', 'Segoe UI'],
    fontSizeUnits: ['px', 'pt'],
    styleTags: ['p', {
      title: 'Blockquote',
      tag: 'blockquote',
      className: 'blockquote',
      value: 'blockquote'
    }, 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
    toolbar: [['style'], ['font', ['bold', 'underline', 'clear', 'font']], ['fontname', ['fontname']], ['fontsize', ['fontsize']], ['color', ['color']], ['para', ['ul', 'ol', 'paragraph']], ['table'], ['insert', ['link']], ['view', ['fullscreen', 'codeview', 'help']]]
  });
  $('.select').select2({
    placeholder: "Selecione uma opção",
    theme: "bootstrap"
  });
  $('.select-attr').select2({
    theme: "bootstrap"
  });
  $(function () {
    $('[data-toggle="tooltip"]').tooltip();
  });
  $('#colorpicker').colorpicker();
})(jQuery, window, document);

/***/ }),

/***/ 1:
/*!******************************************!*\
  !*** multi ./resources/js/admin/main.js ***!
  \******************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! C:\xampp\htdocs\climaup\resources\js\admin\main.js */"./resources/js/admin/main.js");


/***/ })

/******/ });