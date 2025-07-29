const { test, expect, chromium } = require("@playwright/test");
const utils = require("./utils.js");

/** @type {import('@playwright/test').Page} */
let page;
const wait_secs = 2000;

test.beforeAll(async () => {
  const browser = await chromium.launch();
  page = await browser.newPage();
});

test.afterAll(async () => {
  await page.close();
});

test.describe.serial("Create Membership Type", () => {
  var organization = utils.makeid(10);
  const membership_type = `MembershipTypeForTest${Math.floor(
    Math.random() * 100000
  )}`; // Use random number to avoid duplicate names
  const profile_name = `ProfileNameForTest${Math.floor(
    Math.random() * 100000
  )}`; // Use random number to avoid duplicate names
  const contribution_page_name = `ContributionPageNameForTest${Math.floor(
    Math.random() * 100000
  )}`; // Use random number to avoid duplicate names
  var element;
  test("Create Organization Contact", async () => {
    await page.goto("civicrm/contact/add?reset=1&ct=Organization");
    await utils.wait(wait_secs);

    await page.getByLabel("Organization Name").fill(organization);
    await page
      .locator("form[name=Contact] input[type=submit][value='Save']")
      .first()
      .click();
    await utils.wait(wait_secs);
    await expect(page).toHaveTitle(new RegExp("^" + organization));
  });

  test("Create Membership Type", async () => {
    await page.goto("civicrm/admin/member/membershipType?action=add&reset=1");
    await utils.wait(wait_secs);

    await page.getByLabel("Name\n     *").click();
    await page.getByLabel("Name\n     *").fill(membership_type);
    await page.getByLabel("Membership Organization").fill(organization);
    await page.locator("#_qf_MembershipType_refresh").click();
    await utils.wait(wait_secs);

    await page
      .getByRole("combobox", { name: "Contribution Type" })
      .selectOption("2");
    await page.locator("#duration_interval").click();
    await page.locator("#duration_interval").fill("1");
    await page.getByRole("combobox", { name: "Duration" }).selectOption("year");
    await page
      .getByRole("combobox", { name: "Period Type" })
      .selectOption("rolling");
    await page.locator('[id="_qf_MembershipType_upload-bottom"]').click();
    await expect(page.getByRole("cell", { name: membership_type })).toHaveText(
      membership_type
    );
  });
  test("Create Membership", async () => {
    var firstName = "test_firstName";
    var lastName = "test_lastName";
    var name = firstName + " " + lastName;
    // go to create membership page
    await page.goto(
      "/civicrm/member/add?reset=1&action=add&context=standalone"
    );

    // create individual data
    await page.locator("#profiles_1").selectOption("4");
    await expect(page.getByRole("dialog")).toBeVisible();

    await page.locator("#first_name").fill(firstName);
    await page.locator("#last_name").fill(lastName);
    await page.locator("#_qf_Edit_next").click();
    await expect(page.locator("#contact_1")).toHaveValue(name);

    // select the first option in the membership type and orginization
    element = page.locator('[id="membership_type_id\\[0\\]"]');
    // await utils.selectOption(page.locator(element), { index: 0 });
    await element.selectOption(organization);
    element = page.locator('[id="membership_type_id\\[1\\]"]');
    await element.selectOption(membership_type);
    // pick the first date
    await page.locator("#join_date").click();
    await page.getByRole("link", { name: "1", exact: true }).click();
    await page.locator("#start_date").click();
    await page.getByRole("link", { name: "1", exact: true }).click();
    await page.locator('[id="_qf_Membership_upload-bottom"]').click();
    // await utils.wait(wait_secs);
    await expect(page).toHaveTitle(name + " | netiCRM");
    await expect(page.locator("#option11>tbody")).toContainText(
      membership_type
    );
  });
  test("Create Profile", async ({ page }) => {
    await page.goto("/civicrm/admin/uf/group?reset=1");
    
    // 1. Create new profile
    await page.locator("#newCiviCRMProfile-top").click();
    await page.getByRole("textbox", { name: "Profile Name *" }).fill(profile_name);
    await page.getByRole("checkbox", { name: "Form in Event Registeration" }).uncheck();
    await page.getByText("Advanced options").click();
    await page.getByRole("radio", { name: "Account creation required" }).check();
    await page.locator('[id="_qf_Group_next-bottom"]').click();
    
    // 2. Add profile fields
    const fields = [
      { name: "First Name", button: '[id="_qf_Field_next_new-bottom"]' },
      { name: "Last Name", button: '[id="_qf_Field_next-bottom"]' }
    ];
    
    for (const field of fields) {
      await page.locator('[id="field_name[0]"]').selectOption("Individual");
      await page.getByRole("textbox", { name: "Default - Individual Prefix" }).click();
      await page.getByRole("option", { name: `Default - ${field.name}` }).click();
      await page.locator(field.button).click();
    }
    
    // 3. Verify fields created
    await expect(page.locator("#crm-container")).toContainText("First Name");
    await expect(page.locator("#crm-container")).toContainText("Last Name");
  });
  test("Create Contribution Page", async ({ page }) => {
    await page.goto("/civicrm/admin/contribute/add?reset=1&action=add");
    
    // 1. Basic settings
    await page.getByRole("checkbox", { name: "Is this Online Contribution" }).click();
    await page.getByRole("textbox", { name: "Title *" }).fill(contribution_page_name);
    await page.getByLabel("Contribution Type *").selectOption("4");
    await page.locator('[id="_qf_Settings_upload-bottom"]').click();
    
    // 2. Amount configuration
    await page.getByRole("checkbox", { name: "Contribution Amounts section" }).uncheck();
    await page.getByRole("checkbox", { name: "Execute real-time monetary" }).check();
    await page.getByRole("checkbox", { name: "Pay later option" }).check();
    await page.getByRole("textbox", { name: "Pay later instructions" }).fill("I will send payment by check");
    await page.locator('[id="_qf_Amount_upload-bottom"]').click();
    
    // 3. Membership settings
    await page.getByRole("checkbox", { name: "Membership Section Enabled?" }).check();
    await page.getByRole("checkbox", { name: membership_type, exact: true }).check();
    await page.getByRole("checkbox", { name: "Require Membership Signup" }).check();
    await page.locator('[id="_qf_MembershipBlock_upload-bottom"]').click();
    
    // 4. Thank you page
    await page.getByRole("textbox", { name: "Thank-you Page Title *" }).fill("thank");
    await page.locator('[id="_qf_ThankYou_upload-bottom"]').click();
    await page.locator('[id="_qf_Contribute_upload-bottom"]').click();
    
    // 5. Select created profile
    await page.waitForTimeout(1000);
    const options = await page.getByLabel("Include Profile(top of page)").locator('option').all();
    for (let i = 0; i < options.length; i++) {
      const text = await options[i].textContent();
      if (text && text.includes(profile_name)) {
        await page.getByLabel("Include Profile(top of page)").selectOption({ index: i });
        break;
      }
    }
    
    // 6. Complete setup
    const steps = ['Custom', 'Premium', 'Widget', 'PCP'];
    for (const step of steps) {
      await page.locator(`[id="_qf_${step}_upload-${step === 'Widget' || step === 'PCP' ? 'top' : 'bottom'}"]`).click();
    }
    
    await expect(page).toHaveTitle(new RegExp(`Dashlets - ${contribution_page_name}`));
  });

  test("Fill Contribution Form", async ({ page }) => {
    // Open contribution form page
    await page.goto("/civicrm/admin/contribute?reset=1");
    await page.getByRole("link", { name: contribution_page_name }).click();
    
    const [contributionPage] = await Promise.all([
      page.waitForEvent("popup"),
      page.getByRole("link", { name: "» Go to this LIVE Online" }).click()
    ]);
    
    // Fill personal info if not logged in
    const email = contributionPage.locator("#email-5");
    if (await email.isVisible() && await email.isEditable()) {
      await email.fill("test@example.com");
      await contributionPage.locator("#first_name").fill("TestFirstName");
      await contributionPage.locator("#last_name").fill("TestLastName");
    }
    
    // Complete form flow
    await contributionPage.getByRole("button", { name: "Next >>" }).click();
    await expect(contributionPage.getByText("To complete this transaction, click the Continue button below")).toBeVisible();
    await contributionPage.getByRole("button", { name: "Continue >>" }).click();
    await expect(contributionPage.getByText("Keep supporting it. Payment has not been completed yet with entire process.")).toBeVisible();
  });
});
