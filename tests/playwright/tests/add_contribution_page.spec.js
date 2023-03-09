const { test, expect, chromium } = require('@playwright/test');

/** @type {import('@playwright/test').Page} */
let page;
var locator, element;

function makeid(length) {
    var result           = '';
    var characters       = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    var charactersLength = characters.length;
    for ( var i = 0; i < length; i++ ) {
       result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }
    return result;
}

async function findElement(element){
    await expect(page.locator(element)).not.toHaveCount(0);
    console.log('Find an element matching: ' + element);
}

async function fillInput(locator, text_input){
    await expect(locator).toBeEnabled();
    await locator.click();
    await locator.fill(text_input);
    await expect(locator).toHaveValue(text_input);
}

async function checkInput(locator){
    await expect(locator).toBeEnabled();
    await locator.click();
    await expect(locator).toBeChecked();
}

test.beforeAll(async () => {
    const browser = await chromium.launch({"headless": false});
    page = await browser.newPage();
});
  
test.afterAll(async () => {
    await page.close();
});

test.describe.serial('Contribution Page Editing', () => {

    test.use({ storageState: 'storageState.json' });

    test('New Contribution Page test', async () => {

        await test.step('Enter "New Contribution Page" Page.', async () =>{
            /* open add contribution page */
            await page.goto('civicrm/admin/contribute/add?reset=1&action=add');
        });

        /* Step 1: Title */
        await test.step('Title.', async () =>{

            /* fill Title */
            element = "form#Settings input[name='title']";
            locator = page.locator(element);
            await findElement(element);
            await fillInput(locator, makeid(10));

            /* click Continue >> */
            element = "#_qf_Settings_upload-bottom";
            await findElement(element);
            await page.locator(element).click();
            await expect(page.locator('form#Amount')).not.toHaveCount(0);

        });

        /* Step 2: Amounts */
        await test.step('Amounts.', async () =>{

            /* click pay later */
            element = '#is_pay_later';
            await findElement(element);
            await page.locator(element).click();

            /* fill Pay later instructions */
            element = '#pay_later_receipt';
            locator = page.locator(element);
            await findElement(element);
            await fillInput(locator, 'I will send payment by check');

            /* fill Fixed Contribution Options */
            element = "form#Amount input[name='label[1]']";
            locator = page.locator(element);
            await findElement(element);
            await fillInput(locator, '100');

            element = "form#Amount input[name='value[1]']";
            locator = page.locator(element);
            await findElement(element);
            await fillInput(locator, '100');

            element = "form#Amount input[name='label[2]']";
            locator = page.locator(element);
            await findElement(element);
            await fillInput(locator, '200');

            element = "form#Amount input[name='value[2]']";
            locator = page.locator(element);
            await findElement(element);
            await fillInput(locator, '200');

            /* click submit  */
            element = "#_qf_Amount_upload-bottom";
            await findElement(element);
            await page.locator(element).click();
            await expect(page.locator('form#MembershipBlock')).not.toHaveCount(0);

        });

        /* Step 3: Memberships */
        await test.step('Memberships.', async () =>{

            /* click submit */
            element = "#_qf_MembershipBlock_upload-bottom";
            await findElement(element);
            await page.locator(element).click();
            await expect(page.locator('form#ThankYou')).not.toHaveCount(0);

        });

        /* Step 4: Thanks */
        await test.step('Thanks.', async () =>{

            /* fill Thank-you Page Title */
            element = '#thankyou_title';
            locator = page.locator(element);
            await findElement(element);
            await fillInput(locator, makeid(10));

            /* Select Option of Payment Notification From Email */
            element = '#receipt_from_email';
            locator = page.locator(element);
            await findElement(element);
            locator = page.locator(element);
            await expect(locator).toBeEnabled();
            await locator.selectOption({ index: 1 });
            await expect(locator).not.toHaveValue('');

            /* click submit */
            element = "#_qf_ThankYou_upload-bottom";
            await findElement(element);
            await page.locator(element).click();
            await expect(page.locator('form#Contribute')).not.toHaveCount(0);

        });

        /* Step 5: Tell */
        await test.step('Tell.', async () =>{

            /* click Tell a Friend enabled? */
            element = "#tf_is_active";
            await findElement(element);
            await checkInput(page.locator(element));

            /* click submit */
            element = "#_qf_Contribute_upload-bottom";
            await findElement(element);
            await page.locator(element).click();
            await expect(page.locator('form#Custom')).not.toHaveCount(0);

        });

        /* Step 6: Include */
        await test.step('Include.', async () =>{

            /* select Include Profile(top of page) */
            element = "#custom_pre_id";
            await findElement(element);
            await page.locator(element).selectOption('14');
            await expect(page.locator(element)).toHaveValue('14');

            /* select Include Profile(bottom of page) */
            element = "#custom_post_id";
            await findElement(element);
            await page.locator(element).selectOption('14');
            await expect(page.locator(element)).toHaveValue('14');

            /* click submit */
            element = "#_qf_Custom_upload-bottom";
            await findElement(element);
            await page.locator(element).click();
            await expect(page.locator('form#Premium')).not.toHaveCount(0);

        });

        /* Step 7: Premimums */
        await test.step('Premimums.', async () =>{

            /* click Premiums Section Enabled? */
            element = "#premiums_active";
            await findElement(element);
            await checkInput(page.locator(element));

            /* fill Title */
            element = '#premiums_intro_title';
            locator = page.locator(element);
            await findElement(element);
            await fillInput(locator, makeid(10));

            /* click submit */
            element = "#_qf_Premium_upload-bottom";
            await findElement(element);
            await page.locator(element).click();
            await expect(page.locator('form#Widget')).not.toHaveCount(0);

        });

        /* Step 8: Widget */
        await test.step('Widget.', async () =>{

            /* click Enable Widget? */
            element = "#is_active";
            await findElement(element);
            await checkInput(page.locator(element));

            /* filled up About(ckeditor) */
            element = 'iframe.cke_wysiwyg_frame';
            await findElement(element);
            const frame_locator = page.frameLocator(element);
            await frame_locator.locator(':nth-match(p,1)').click({ position: { x: 0, y: 0 } });
            await page.keyboard.type('widget test');
            await expect(frame_locator.locator(':nth-match(p,1)')).toHaveText('widget test');

            /* click Save and Preview */
            element = "#_qf_Widget_refresh";
            await findElement(element);
            await page.locator(element).first().click();

            /* check if widget iframe exist */
            await findElement('iframe.crm-container-embed');

            /* click submit */
            element = "#_qf_Widget_upload-bottom";
            await findElement(element);
            await page.locator(element).click();
            await expect(page.locator('form#PCP')).not.toHaveCount(0);

        });

        /* Step 9: Enable */
        await test.step('Enable.', async () =>{

            /* click Enable Personal Campaign Pages (for this contribution page)? */
            element = "#is_active";
            await findElement(element);
            await checkInput(page.locator(element));

            /* fill Notify Email */
            element = '#notify_email';
            locator = page.locator(element);
            await findElement(element);
            await fillInput(locator, makeid(5) + '@fakemail.com');

            /* click submit */
            element = "#_qf_PCP_upload-bottom";
            await findElement(element);
            await page.locator(element).click();
            await expect(page.locator('table.report')).not.toHaveCount(0);

        });

    });

});