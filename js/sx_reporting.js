OBModules.SxReporting = new function()
{

  this.init = function()
  {
    OB.Callbacks.add('ready',0,OBModules.SxReporting.initMenu);
  }

  this.initMenu = function()
  {
    OB.UI.addSubMenuItem('admin','SX Reporting','sx_reporting',OBModules.SxReporting.open,150,'sx_reporting_module');
  }
  
  this.open = function()
  {
    OB.UI.replaceMain('modules/sx_reporting/sx_reporting.html');
    $('#sx_reporting_start').datepicker({ dateFormat: "yy-mm-dd" });
    $('#sx_reporting_end').datepicker({ dateFormat: "yy-mm-dd" });
    
    OB.API.post('player','player_list', {}, function(data)
    {
      var players = data.data;

      $.each(players,function(index,item) {
        $('#sx_reporting_player').append('<option value="'+item.id+'">'+htmlspecialchars(item.name)+'</option>');
      });
      
      $.each(OB.Settings.categories,function(index,category) {
        $('#sx_reporting_media_category').append('<option value="'+category.id+'">'+htmlspecialchars(category.name)+'</option>');
      });
      
      // add metadata field options for isrc and marketing label
      $.each(OB.Settings.media_metadata, function(index,metadata) {
        $('#sx_reporting_isrc').add('#sx_reporting_label').append( $('<option></option>').text(metadata.description).attr('value',metadata.name) );
        $('#sx_reporting_additional_fields').append( $('<option></option>').text(metadata.description).attr('value','metadata_'+metadata.name) );  
      });
      
      // take an educated guess at metadata field options
      if($('#sx_reporting_isrc option[value=isrc]')) $('#sx_reporting_isrc').val('isrc');
      if($('#sx_reporting_label option[value=marketing_label]')) $('#sx_reporting_label').val('marketing_label');
      else if($('#sx_reporting_label option[value=label]')) $('#sx_reporting_label').val('label');
    });
  }
  
  this.generate = function()
  {
    $('#sx_reporting_message').obWidget('info','Generating report. This may take a few minutes.');
    $('#sx_reporting_file').hide();
  
    var post = {};
    post.player = $('#sx_reporting_player').val();
    post.start = $('#sx_reporting_start').val();
    post.end = $('#sx_reporting_end').val();
    post.service_name = $('#sx_reporting_service_name').val();
    post.transmission_category = $('#sx_reporting_transmission_category').val();
    post.media_category = $('#sx_reporting_media_category').val();
    post.isrc = $('#sx_reporting_isrc').val();
    post.label = $('#sx_reporting_label').val();
    post.tuning_hours = $('#sx_reporting_tuning_hours').val();
    post.additional_fields = $('#sx_reporting_additional_fields').val();
    
    OB.API.post('sxreporting','generate',post,function(response)
    {
      
      $('#sx_reporting_message').obWidget(response.status ? 'success' : 'error',response.msg);
      
      if(response.status)
      {
        $('#sx_reporting_file').show();
        var data = new Blob([response.data], { type: 'application/octect-stream' });
        var url = URL.createObjectURL(data);
        var filename = 'sx_report.csv';
        $('#sx_reporting_file').attr({
          href: url,
          download: filename
        }); 
      }
      
    });
  }
  
  this.download = function()
  {
    $('#sx_reporting_file').click();
  }
}
  