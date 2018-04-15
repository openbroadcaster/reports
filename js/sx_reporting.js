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
    
    OB.API.post('device','device_list', {}, function(data)
    {
      var devices = data.data;

      $.each(devices,function(index,item) {
        $('#sx_reporting_device').append('<option value="'+item.id+'">'+htmlspecialchars(item.name)+'</option>');
      });
      
      $.each(OB.Settings.categories,function(index,category) {
        $('#sx_reporting_media_category').append('<option value="'+category.id+'">'+htmlspecialchars(category.name)+'</option>');
      });
    });
  }
  
  this.generate = function()
  {
    $('#sx_reporting_message').obWidget('info','Generating report. This may take a few minutes.');
    $('#sx_reporting_file').hide();
  
    var post = {};
    post.device = $('#sx_reporting_device').val();
    post.start = $('#sx_reporting_start').val();
    post.end = $('#sx_reporting_end').val();
    post.service_name = $('#sx_reporting_service_name').val();
    post.transmission_category = $('#sx_reporting_transmission_category').val();
    post.media_category = $('#sx_reporting_media_category').val();
      
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
  