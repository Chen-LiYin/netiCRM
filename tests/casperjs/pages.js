casper.options.waitTimeout = 10000;

var system = require('system'); 
var port = system.env.RUNPORT; 

var vars = {
  testNum: 0,
  baseURL: system.env.CASPERTEST_BASEURL+'/',
  startURL: system.env.CASPERTEST_STARTURL,
  siteName: system.env.CASPERTEST_SITENAME,
  username: system.env.DRUPAL_USERNAME,
  password: system.env.DRUPAL_PASSWORD,

// you should add your own testing variables below
  url: [
    {title:'New Individual', url:'civicrm/contact/add?reset=1&ct=Individual'},
    {title:'Custom Data', url:'civicrm/admin/custom/group?reset=1'},
    {title:'New Activity', url:'civicrm/activity?reset=1&action=add&context=standalone'},
    {title:'Administer CiviCRM', url:'civicrm/admin?reset=1'},
    {title:'CiviCRM Home', url:'civicrm/dashboard'},
    {title:'Custom Data', url:'civicrm/admin/custom/group?reset=1'},
    {title:'CiviCRM Profile', url:'civicrm/admin/uf/group?reset=1'},
    {title:'Configuration Checklist', url:'civicrm/civicrm/admin/configtask?reset=1'},
    {title:'Synchronize Users to Contacts', url:'civicrm/admin/synchUser?reset=1'},
    {title:'Find Contacts', url:'civicrm/contact/search?reset=1'},
    {title:'New Individual', url:'civicrm/contact/add?reset=1&ct=Individual'},
    {title:'New Organization', url:'civicrm/contact/add?reset=1&ct=Organization'},
    {title:'New Household', url:'civicrm/contact/add?reset=1&ct=Household'},
    {title:'Activities', url:'civicrm/activity/add?atype=3&action=add&reset=1&context=standalone'},
    {title:'Import Contacts', url:'civicrm/import/contact?reset=1'},
    {title:'Manage Groups', url:'civicrm/group?reset=1'},
    {title:'Manage Tags (Categories)', url:'civicrm/admin/tag?reset=1'},
    {title:'New Activity', url:'civicrm/activity?reset=1&action=add&context=standalone'},
    {title:'Find Activities', url:'civicrm/activity/search?reset=1'},
    {title:'Import Activities', url:'civicrm/import/activity?reset=1'},
    {title:'Find and Merge Duplicate Contacts', url:'civicrm/contact/deduperules?reset=1'},
    {title:'Relationship Types', url:'civicrm/admin/reltype?reset=1'},
    {title:'CiviCRM Profile', url:'civicrm/admin/uf/group?reset=1'},
    {title:'Custom Data', url:'civicrm/admin/custom/group?reset=1'},
    {title:'CiviContribute Dashboard', url:'civicrm/contribute?reset=1'},
    {title:'Payment Instrument Options', url:'civicrm/admin/options/payment_instrument?group=payment_instrument&reset=1'},
    {title:'New Contribution', url:'civicrm/contribute/add?reset=1&action=add&context=standalone'},
    {title:'Find Contributions', url:'civicrm/contribute/search?reset=1'},
    {title:'Import Contributions', url:'civicrm/contribute/import?reset=1'},
    {title:'CiviPledge', url:'civicrm/pledge?reset=1'},
    {title:'New Pledge', url:'civicrm/pledge/add?reset=1&action=add&context=standalone'},
    {title:'Find Pledges', url:'civicrm/pledge/search?reset=1'},
    {title:'Title and Settings', url:'civicrm/admin/contribute/add?reset=1&action=add'},
    {title:'Manage Contribution Pages', url:'civicrm/admin/contribute?reset=1'},
    {title:'Personal Campaign Pages', url:'civicrm/admin/pcp?reset=1'},
    {title:'Manage Premiums', url:'civicrm/admin/contribute/managePremiums?reset=1'},
    {title:'New Price Set', url:'civicrm/admin/price?reset=1&action=add'},
    {title:'Price Sets', url:'civicrm/admin/price?reset=1'},
    {title:'Contribution Types', url:'civicrm/admin/contribute/contributionType?reset=1'},
    {title:'CiviEvent Dashboard', url:'civicrm/event?reset=1'},
    {title:'Event Type Options', url:'civicrm/admin/options/event_type?group=event_type&reset=1'},
    {title:'Participant Status', url:'civicrm/admin/participant_status?reset=1'},
    {title:'Participant Role Options', url:'civicrm/admin/options/participant_role?group=participant_role&reset=1'},
    {title:'Register New Participant', url:'civicrm/participant/add?reset=1&action=add&context=standalone'},
    {title:'Find Participants', url:'civicrm/event/search?reset=1'},
    {title:'Import Participants', url:'civicrm/event/import?reset=1'},
    {title:'New Event', url:'civicrm/event/add?reset=1&action=add'},
    {title:'CiviEvent Dashboard', url:'civicrm/event/manage?reset=1'},
    {title:'Event Templates', url:'civicrm/admin/eventTemplate?reset=1'},
    {title:'New Price Set', url:'civicrm/admin/price?reset=1&action=add'},
    {title:'Price Sets', url:'civicrm/admin/price?reset=1'},
    {title:'Find Mailings', url:'civicrm/mailing/browse?reset=1&scheduled=true'},
    {title:'New Mailing', url:'civicrm/mailing/send?reset=1'},
    {title:'Draft and Unscheduled Mailings', url:'civicrm/mailing/browse/unscheduled?reset=1&scheduled=false'},
    {title:'Scheduled and Sent Mailings', url:'civicrm/mailing/browse/scheduled?reset=1&scheduled=true'},
    {title:'Archived Mailings', url:'civicrm/mailing/browse/archived?reset=1'},
    {title:'Headers, Footers, and Automated Messages', url:'civicrm/admin/component?reset=1'},
    {title:'Message Templates', url:'civicrm/admin/messageTemplates?reset=1'},
    {title:'From Email Address Options', url:'civicrm/admin/options/from_email?group=from_email_address&reset=1'},
    {title:'Email Greeting Options', url:'civicrm/admin/options/email_greeting?group=email_greeting&reset=1'},
    {title:'CiviMember', url:'civicrm/member?reset=1'},
    {title:'New Member', url:'civicrm/member/add?reset=1&action=add&context=standalone'},
    {title:'Find Members', url:'civicrm/member/search?reset=1'},
    {title:'Import Memberships', url:'civicrm/member/import?reset=1'},
    {title:'Membership Types', url:'civicrm/admin/member/membershipType?reset=1'},
    {title:'Membership Status Rules', url:'civicrm/admin/member/membershipStatus?reset=1'},
    {title:'CiviCRM Reports', url:'civicrm/report/list?reset=1'},
    {title:'Create Reports from Templates', url:'civicrm/admin/report/template/list?reset=1'},
    {title:'Registered Templates', url:'civicrm/admin/report/options/report_template?reset=1'},
    {title:'Search Builder', url:'civicrm/contact/search/builder?reset=1'},
    {title:'全文搜尋', url:'civicrm/contact/search/custom?csid=15&reset=1'},
    {title:'Advanced Search', url:'civicrm/contact/search/advanced?reset=1'}
  ],
};
vars.testNum = vars.url.length*2+1;


var lookup_title = function(u){
  for(var i in vars.url){
    if(vars.url[i].url == u){
      return vars.url[i].title;
    }
  }
};

casper.test.begin('Page output correct test', vars.testNum, function suite(test) {
  casper.start(vars.startURL, function() {
    test.assertExists('#user-login-form', "Found login form");
    this.fill('#user-login-form', {
      'name': vars.username,
      'pass': vars.password
    }, true);
  });

  casper.wait(2000);
  casper.waitForSelector('body', function(){
    for(var i in vars.url){
      casper.thenOpen(vars.baseURL + vars.url[i].url, function(obj){
        if(obj.url){
          var url = obj.url.replace(vars.baseURL, '');
          var title = lookup_title(url);
          var full_title = title + ' | ' + vars.siteName;
          test.assertTitle(full_title, title + ' should match page title');
          test.assertDoesntExist('.error-ci', title + ' page have no error');
        }
      });
    }
  });

  casper.run(function(){
    test.done();
  });
});
