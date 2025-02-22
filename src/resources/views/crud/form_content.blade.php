<input type="hidden" name="_http_referrer" value={{ session('referrer_url_override') ?? old('_http_referrer') ?? \URL::previous() ?? url($crud->route) }}>

{{-- See if we're using tabs --}}
@if ($crud->tabsEnabled() && count($crud->getTabs()))
    @include('crud::inc.show_tabbed_fields')
    <input type="hidden" name="_current_tab" value="{{ Str::slug($crud->getTabs()[0]) }}" />
@else
  <div class="card">
    <div class="card-body row">
      @include('crud::inc.show_fields', ['fields' => $crud->fields()])
    </div>
  </div>
@endif


{{-- Define blade stacks so css and js can be pushed from the fields to these sections. --}}

@section('after_styles')

    {{-- CRUD FORM CONTENT - crud_fields_styles stack --}}
    @stack('crud_fields_styles')

@endsection

@section('after_scripts')

    {{-- CRUD FORM CONTENT - crud_fields_scripts stack --}}
    @stack('crud_fields_scripts')

    <script>
    function initializeFieldsWithJavascript(container) {
      var selector;
      if (container instanceof jQuery) {
        selector = container;
      } else {
        selector = $(container);
      }
      selector.find("[data-init-function]").not("[data-initialized=true]").each(function () {
        var element = $(this);
        var functionName = element.data('init-function');

        if (typeof window[functionName] === "function") {
          window[functionName](element);

          // mark the element as initialized, so that its function is never called again
          element.attr('data-initialized', 'true');
        }
      });
    }

    /**
     * Auto-discover first focusable input
     * @param {jQuery} form
     * @return {jQuery}
     */
    function getFirstFocusableField(form) {
        return form.find('input, select, textarea, button')
            .not('.close')
            .not('[disabled]')
            .filter(':visible:first');
    }

    /**
     *
     * @param {jQuery} firstField
     */
    function triggerFocusOnFirstInputField(firstField) {
        if (firstField.hasClass('select2-hidden-accessible')) {
            return handleFocusOnSelect2Field(firstField);
        }

        firstField.trigger('focus');
    }

    /**
     * 1- Make sure no other select2 input is open in other field to focus on the right one
     * 2- Check until select2 is initialized
     * 3- Open select2
     *
     * @param {jQuery} firstField
     */
    function handleFocusOnSelect2Field(firstField){
        $('.select2-search__field').remove();
        firstField.select2('open');
    }

    /*
    * Hacky fix for a bug in select2 with jQuery 3.6.0's new nested-focus "protection"
    * see: https://github.com/select2/select2/issues/5993
    * see: https://github.com/jquery/jquery/issues/4382
    *
    */
    $(document).on('select2:open', () => {
        document.querySelector('.select2-search__field').focus();
    });

    jQuery('document').ready(function($){

      // trigger the javascript for all fields that have their js defined in a separate method
      initializeFieldsWithJavascript('form');

      // Retrieves the current form data
      function getFormData() {
        return new URLSearchParams(new FormData(document.querySelector("main form"))).toString();
      }

      // Prevents unloading of page if form data was changed
      function preventUnload(event) {
        if (initData !== getFormData()) {
          // Cancel the event as stated by the standard.
          event.preventDefault();
          // Older browsers supported custom message
          event.returnValue = '';
        }
      }

      @if($crud->getOperationSetting('warnBeforeLeaving'))
      const initData = getFormData();
      window.addEventListener('beforeunload', preventUnload);
      @endif

      // Save button has multiple actions: save and exit, save and edit, save and new
      var saveActions = $('#saveActions'),
      crudForm        = saveActions.parents('form'),
      saveActionField = $('[name="_save_action"]');

      saveActions.on('click', '.dropdown-menu a', function(){
          var saveAction = $(this).data('value');
          saveActionField.val( saveAction );
          crudForm.submit();
      });

      // Ctrl+S and Cmd+S trigger Save button click
      $(document).keydown(function(e) {
          if ((e.which == '115' || e.which == '83' ) && (e.ctrlKey || e.metaKey))
          {
              e.preventDefault();
              $("button[type=submit]").trigger('click');
              return false;
          }
          return true;
      });

      // prevent duplicate entries on double-clicking the submit form
      crudForm.submit(function (event) {
        window.removeEventListener('beforeunload', preventUnload);
        $("button[type=submit]").prop('disabled', true);
      });

      // Place the focus on the first element in the form
      @if( $crud->getAutoFocusOnFirstField() )
        @php
          $focusField = Arr::first($fields, function($field) {
              return isset($field['auto_focus']) && $field['auto_focus'] == true;
          });
        @endphp

        let focusField;

        @if ($focusField)
          @php
            $focusFieldName = isset($focusField['value']) && is_iterable($focusField['value']) ? $focusField['name'] . '[]' : $focusField['name'];
          @endphp
            focusField = $('[name="{{ $focusFieldName }}"]').eq(0);
        @else
            focusField = getFirstFocusableField($('form'));
        @endif

        const fieldOffset = focusField.offset().top;
        const scrollTolerance = $(window).height() / 2;

        triggerFocusOnFirstInputField(focusField);

        if( fieldOffset > scrollTolerance ){
            $('html, body').animate({scrollTop: (fieldOffset - 30)});
        }
      @endif

      // Add inline errors to the DOM
      @if ($crud->inlineErrorsEnabled() && $errors->any())

        window.errors = {!! json_encode($errors->messages()) !!};

        $.each(errors, function(property, messages){

            var normalizedProperty = property.split('.').map(function(item, index){
                    return index === 0 ? item : '['+item+']';
                }).join('');

            var field = $('[name="' + normalizedProperty + '[]"]').length ?
                        $('[name="' + normalizedProperty + '[]"]') :
                        $('[name="' + normalizedProperty + '"]'),
                        container = field.parents('.form-group');

            // iterate the inputs to add invalid classes to fields and red text to the field container.
            container.children('input, textarea, select').each(function() {
                let containerField = $(this);
                // add the invalida class to the field.
                containerField.addClass('is-invalid');
                // get field container
                let container = containerField.parent('.form-group');

                // if container is a repeatable group we don't want to add red text to the whole group,
                // we only want to add it to the fields that have errors inside that repeatable.
                if(!container.hasClass('repeatable-group')){
                  container.addClass('text-danger');
                }
            });

            $.each(messages, function(key, msg){
                // highlight the input that errored
                var row = $('<div class="invalid-feedback d-block">' + msg + '</div>');
                row.appendTo(container);

                // highlight its parent tab
                @if ($crud->tabsEnabled())
                var tab_id = $(container).closest('[role="tabpanel"]').attr('id');
                $("#form_tabs [aria-controls="+tab_id+"]").addClass('text-danger');
                @endif
            });
        });

      @endif

      $("a[data-toggle='tab']").click(function(){
          currentTabName = $(this).attr('tab_name');
          $("input[name='_current_tab']").val(currentTabName);
      });

      if (window.location.hash) {
          $("input[name='_current_tab']").val(window.location.hash.substr(1));
      }
      });
    </script>

    @include('crud::inc.form_fields_script')
@endsection
