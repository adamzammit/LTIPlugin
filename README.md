# LTIPlugin
LimeSurvey Plugin that allows LimeSurvey to act as an LTI provider for tools such as Moodle, Canvas, openEdX and Blackboard. LimeSurvey will have access to the LMS course name and course and student identifier and allow the completion of a survey.
This plugin can also be used to return a grade/score/result back to the LMS based on a LimeSurvey expression. Therefore this plugin can be used to administer an exam or quiz in LimeSurvey which calculates a score and returns it automatically to the LMS.

## Installation

Download the zip from the [releases](https://github.com/adamzammit/LTIPlugin/releases) page and extract to your plugins folder. You can also clone directly from git: go to your plugins directory and type
```
git clone --recursive https://github.com/adamzammit/LTIPlugin.git LTIPlugin
```

## Requirements

- LimeSurvey version 3.x, 4.x, 5.x, 6.x
- Surveys need to be activated, with a participant table set up with at least 4 attributes avaiable, 7 attributes if you want to return a grade/result (the plugin will use the first 4 or 7 attributes for LTI related data)
- If your LTI Provider is running on HTTPS, then LimeSurvey must run over HTTPS also

## LimeSurvey Particpant Attributes

Required:
- attribute_1: LTI return URL
- attribute_2: LMS Course Title
- attribute_3: LMS Resource ID (course ID)
- attribute_4: LMS User ID

Optional (if using LimeSurvey to return a grade/result)
- attribute_5: LMS result source did
- attribute_6: LMS outcome source URL
- attribute_7: Storing the result of the attempt to set a grade in the LMS
 

## Configuration (LimeSurvey)

1. Visit the "Plugin manager" section in your LimeSurvey installation under "Configuration"
2. Confirm the LTI attributes match the system you wish to use (examples are given for OpenEdX and Canvas, also you can use Debug mode if you want to discover these yourself for testing in your own system)
3. Save the settings
4. Activate the plugin
5. Visit the "Global settings" section, tab called "Security" in your LimeSurvey installation under "Configuration"
6. Ensure "IFrame embedding allowed:" is set to "Allow"
7. Activate an existing or new survey
8. Visit "Simple plugin settings" for the survey and choose "Settings for plugin LTIPlugin"
9. A random key and password should be generated - save the settings then a URL to access should be displayed (otherwise a message will be displayed notifying of the requirements for the LTI plugin as above)
10. Use the URL listed and the key and secret generated to set up your LMS to use LimeSUrvey as an LTI Provider (see below for examples)
11. By default a course participant will be able to complete the survey only once, and will return to the previous point of completion when visiting the survey again if not completed. If you want them to be able to complete multiple times for the same unit - please set "Allow a user in a course to complete this survey more than once" to "Yes"
12. If you want to return a grade/score back to the LMS - enter a text or expression in the return result box. You can just put the number 1 if you want 100% returned on completion, otherwise you can use any valid LimeSurvey expression to send a calculated value (this could be used to send back a score on an exam for example). The value should always be a floating point number between 0.0 and 1.0

Note: See the included file "example-survey-return-assessment-value.lss" to see how you can return the result of an assessment using the plugin

### Configuration and usage (Canvas)

1. Edit your course
2. In your course, visit "Settings" then the tab "Apps", then "View App Configurations", then click on the green "+_App" button
3. Choose "Manual entry" for your configuration type
4. Name the app
5. Copy the consumer key, secret and Launch URL from the LimeSurvey LTIPlugin settings page (under Simple plugins in your LimeSurvey survey)

Now that the application is configured, you can:
1. Add a new item to a module in your course, and choose "External Tool" then the name of your app and don't forget to "publish"
2. If you add as an "Assignment" and you have the return result set in LimeSurvey simple plugin settings, the score will be returned

If you have recieved a "CSRF Token" error in LimeSurvey you may need to check the box "Load in a new tab" when editing the item in Canvas

### Configuration and Usage (Moodle)

1. Add a new "Activity or resource"
2. Choose "External Tool"
3. The "Tool URL" is the URL that appears on the "Settings for plugin LTI Plugin" page for your survey
4. Click on "Show more" under "General"
5. The "Consumer key" is the key that appears on the LTI plugin settings page
6. The "Shared secret" is the secret that appears on the LTI plugin settings page


### Configuration (OpenEdX)

1. Edit your course in OpenEdX "Studio"
2. In your course, visit "Settings" then "Advanced Settings"
3. Ensure "Advanced Module List" contains:
```
    ["lti_consumer"]
```
4. Ensure "LTI Passports" contains:
```
    ["limesurvey:KEY:SECRET"]
```
   (Where KEY and SECRET are replaced with the key and secret generated in the configuration step above - this will also be able to be copied and pasted from the LTIPlugin settings in LimeSurvey)
5. Save the Advanced Settings

If you have recieved a "CSRF Token" error in LimeSurvey you may need to set "LTI Launch Target" to "New Window" in OpenEdX to overcome this.

### Usage (OpenEdX)

1. Add a new "Unit"
2. Choose "Advanced" as the Component (if "Advanced" doesn't appear, check your Configuration settings as above)
3. Click on "LTI Consumer"
4. Click on "Edit"
5. Enter a display name - this can be anything you choose
6. The "LTI ID" should be:
```
    limesurvey
```
7. The "LTI URL" is the URL that appears on the "Settings for plugin LTI Plugin" page for your survey
8. Other settings can remain as default
9. Click "Save" and you will now be able to access LimeSurvey from within

### Configuration (Blackboard)

1. Visit the "Administrator Panel", then under "Integrations" select "LTI Tool Providers"
2. Choose the tab "Manage Global Properties"
3. Ensure "Allow configured tool providers to post grades" is set to "Yes". Submit to save this configuration.
4. Choose the tab "Register LTI 1.1 Provider"
5. Add the domain name of your LimeSurvey installation in "Provider Domain". For "Default Configuration" choose "Set separately for each link". For "Policies" enable "Role in Course", "Name", "Email Address" in "User fields to send". Submit to save this configuration

### Usage (Blackboard)

1. In course content, create a new item "Teaching tools with LTI Connection"
2. The "Configuration URL" is the URL that appears on the "Settings for plugin LTI Plugin" page for your survey
3. Enter the Key as the "Key" and the "Secret" as the "Security Token"
4. If you want to return a result (grade) from LimeSurvey check the box "Create gradebook entry for this item"
5. Click "Save" to make the resource available in the course

If you have received a "CSRF Token" error in LimeSurvey you may need to set "Open in new window" under LTI Link Details to overcome this.

## Common issues

1. If you get a "Bad Signature" error or similar and your service is behind a reverse proxy for SSL, please ensure that the headers X-Forwarded-Proto is set to "https" and X-Forwarded-Port is set to "443" to ensure LimeSurvey will know it is running in an SSL environment

## Security

If you discover any security related issues, please email adam@acspri.org.au instead of using the issue tracker.

## Contributing

PR's are welcome!

## Usage

You are free to use/change/fork this code for your own products (licence is GPLv3), and I would be happy to hear how and what you are using it for!
