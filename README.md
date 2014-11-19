#Xataface FDF Module

##Synopsis

The Xataface FDF module allows developers to design PDF forms using standard tools like Open Office and Adobe Acrobat, and use them as the basis for templates that will be filled in at runtime.  This is handy if you want to generate forms in some standard format, and prefill the fields with data-from your database.

##License

Apache 2.0

##Requirements

1. Xataface 2.0 or higher
2. PDFTK installed on the server.

##Installation using Git

Suppose you want to add this module to your application which is located at `/var/www/myapp` on your server:

~~~
$ cd /var/www/myapp
$ mkdir modules
$ cd modules/
$ git clone https://github.com/shannah/xataface-fdf-module.git fdf
~~~

At this point you should have the *fdf* module installed at `/var/www/myapp/modules/fdf`.

In your application's *conf.ini* file (i.e. `/var/www/myapp/conf.ini`), add the following to the `[_modules]` section:

~~~
modules_fdf=modules/fdf/fdf.php
~~~

E.g. Your modules section will look something like:

~~~
[_modules]
   modules_depselect=modules/depselect/depselect.php
   modules_some_other_mod=modules/some_other_mod/some_other_mod.php
   modules_fdf=modules/fdf/fdf.php
~~~

Reload your application in the web browser and make sure there are no errors.  At this point the module is active, but it isn't being used for anything yet.

##Installation from Release

Suppose you want to add this module to your application which is located at `/var/www/myapp` on your server:

1. Add a `modules` directory if it doesn't exist already.  E.g.

 ~~~
 $ cd /var/www/myapp
 $ mkdir modules
 ~~~
2. Download the [latest release](..) tar or zip file and extract it inside your `modules` directory.
3. Rename the `xataface-fdf-module` directory to `fdf`.


At this point you should have the *fdf* module installed at `/var/www/myapp/modules/fdf`.

In your application's *conf.ini* file (i.e. `/var/www/myapp/conf.ini`), add the following to the `[_modules]` section:

~~~
modules_fdf=modules/fdf/fdf.php
~~~

E.g. Your modules section will look something like:

~~~
[_modules]
   modules_depselect=modules/depselect/depselect.php
   modules_some_other_mod=modules/some_other_mod/some_other_mod.php
   modules_fdf=modules/fdf/fdf.php
~~~

Reload your application in the web browser and make sure there are no errors.  At this point the module is active, but it isn't being used for anything yet.

##Hosting a PDF Form

This section assumes that you already have a PDF form that you wish to use as a template.  This PDF should have some form fields that are named so that Xataface can reference them and fill them in with appropriate data.  For more information about creating PDF forms, see here.  TODO: ADD section on creating PDF forms.

1. Create a `fdf_templates` directory inside your application's main folder.  If your application is located at `/var/www/myapp`, then this directory should be located at `/var/www/myapp/fdf_templates`:
 
 ~~~
 $ cd /var/www/myapp
 $ mkdir fdf_templates
 ~~~
2. Copy your PDF into this directory. In our example, assume this PDF is named "MyPDFTemplate.pdf".  Then is will be located at `/var/www/myapp/fdf_templates/MyPDFTemplate.pdf`


To view this form populated, you will need to point your browser to the `fdf_report` action of your application, with the appropriate Xataface URL conventions to determine which record is used to fill in the fields.  E.g., assume that this report was designed to be used with the *people* table, then the URL you would enter would be something like:

`index.php?-action=fdf_report&--pdf-template=MyPDFTemplate.pdf&--single=1&-table=people&person_id=58`

If everything went well, this will display the MyPDFTemplate.pdf template filled in with the data in the `people` record with `person_id=58`.

For a full description of the supported GET parameters in the `fdf_report` action, see [this section](#fdf-report-parameters).

Now that you know how to generate a report, there are 2 issues that you will probably need to deal with:

1. Configuring which content is filled into which fields of the PDF form.
2. Adding links, buttons, and menus to the application's UI so that users can generate their own reports.


##Mapping Content to PDF Fields

By default, the `fdf_report` will map fields in the selected record to fields in the PDF form template based on the field name.  E.g. The "name" field of the PDF form will be filled in with the content of the "name" field in the selected database record.  Calculated fields are supported if you need to generate content for fields that don't exist in the database.  E.g. If the table has `first_name` and `last_name` fields, but the PDF form has a `full_name` field, then you can create a calculated `full_name` field in your table by implementing the `field__fullname()` method in its delegate class:

~~~
function field__fullname(Dataface_Record $rec){
    return $rec->display('first_name').' '.$rec->display('last_name');
}
~~~

The FDF module also allows you to implement a the `fdf_fill_fields()` delegate class method that will *fill* the data in some custom way.  E.g.:

~~~
    /**
     * A callback used by the fdf module.  It allows us to fill in a PDF form
     * @param Dataface_Record $rec The subject record.
     * @param array @$vals Associative array to be filled.
     * @param string $template_name The name of the PDF template we are working with. 
     *  This is located in the fdf_templates directory.
     */
    function fdf_fill_fields(Dataface_Record $rec, &$vals, $template_name){
        if ( $template_name == 'TSSU_APPOINTMENT_FORM.pdf' ){
            $contact = $rec->val('contact');
            if ( $contact ){
                $vals['first_name'] = $contact->display('first_name');
                $vals['last_name'] = $contact->display('last_name');
                $vals['sin'] = $contact->display('sin');
                $vals['student_number'] = $contact->display('employee_id');
                $vals['appointment_category'] = $contact->display('ta_type');
                
            }
            
            $vals['salary_biweekly_rate_dollars'] = intval($rec->val('stipend_biweekly')) ;
            $vals['salary_biweekly_rate_cents'] =  str_pad(''.floor(
                (floatval($rec->val('stipend_biweekly')) - floor($rec->val('stipend_biweekly'))
            )*100), 2, '0') ;
            
            $vals['salary_semester_rate_dollars'] = intval($rec->val('stipend_total')) ;
            $vals['salary_semester_rate_cents'] =  str_pad(''.floor(
                (floatval($rec->val('stipend_total')) - floor($rec->val('stipend_total'))
            )*100), 2, '0') ;
            
            $vals['scholarship_biweekly_rate_dollars'] = intval($rec->val('scholarship_biweekly')) ;
            $vals['scholarship_biweekly_rate_cents'] =  str_pad(''.floor(
                (floatval($rec->val('scholarship_biweekly')) - floor($rec->val('scholarship_biweekly'))
            )*100), 2, '0') ;
            
            $vals['scholarship_semester_rate_dollars'] = intval($rec->val('scholarship_total')) ;
            $vals['scholarship_semester_rate_cents'] =  str_pad(''.floor(
                (floatval($rec->val('scholarship_total')) - floor($rec->val('scholarship_total'))
            )*100), 2, '0') ;
            
            // etc....
        }
        
        // etc ...
    }
~~~


##`fdf_report` GET Parameters

The `fdf_report` action takes a few special GET parameters to configure how it processes PDFs.  It also accepts the standard Xataface URL conventions for selecting which record should be used to fill in the data.

**Get Parameters:**

| Parameter Name | Description | Required | Default | Since |
|---|---|---|---|---|
| `--pdf-template` | The name of the PDF file that should be used as the template.  This PDF file must be located in the `fdf_templates` directory. | Yes | `null` | 0.1 |
| `--single` | A flag to indicate that only a single PDF should be generated.  If you set this to a truthy value, then the action will output the PDF directly to the browser.  If not, it will output a zip file containing all generated PDFs from the specified found set.  | `0` | 0.1 |

##Adding Reports to the UI

Obviously you won't want your users having to craft a URL to get their reports.  You will probably want to add links or buttons somewhere in the user interface of the app so that users can just click and see their filled PDF.  The preferred way to do this is using [actions](Actions.md#hello-world-action), but you could just as well add links in your UI in any way you choose (e.g. blocks, slots, template customization, etc..).

###Example Adding Custom Action

Here is an example of a custom action that is set up to appear along with the record actions (i.e. along the top bar when showing the details of a particular record) of the `appointments` table of my database:

~~~
[print_appointment_form]
	category="record_actions"
	label="Print Contract"
	description="Print Teaching Assistant Appointment Form"
	condition="$record and $record->table()->tablename == 'appointments' and $record->val('appointment_type') < 5"
       url="{$record->getURL('-action=fdf_report')}&--pdf-template=TSSU_APPOINTMENT_FORM.pdf&--single=1"
	url_condition="$record"
    icon="{$dataface_url}/images/print_icon.gif"
	target="_blank"
	order=100
~~~

The use of `$record->getURL()` allows us to generate the appropriate URL for the current record, rather than making the action go to the same record every time.  You could also achieve similar using the `$app->url()` method.

