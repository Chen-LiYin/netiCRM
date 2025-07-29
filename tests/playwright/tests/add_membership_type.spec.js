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
    // go to create profile page
    await page.goto("/civicrm/admin/uf/group?reset=1");
    await expect(page).toHaveTitle(/CiviCRM Profile/);

    // create new profile
    await page.locator("#newCiviCRMProfile-top").click();
    await expect(page.locator("#Group")).toBeVisible();

    // fill profile name and settings
    await page
      .getByRole("textbox", { name: "Profile Name *" })
      .fill(profile_name);
    await page
      .getByRole("checkbox", { name: "Form in Event Registeration" })
      .uncheck();
    await page.getByText("Advanced options").click();
    await page
      .getByRole("radio", { name: "Account creation required" })
      .check();
    await page.locator('[id="_qf_Group_next-bottom"]').click();
    await expect(page).toHaveTitle(
      new RegExp(`${profile_name} - CiviCRM Profile Fields`)
    );

    // add first name field
    await page.locator('[id="field_name[0]"]').selectOption("Individual");
    await page
      .getByRole("textbox", { name: "Default - Individual Prefix" })
      .click();
    await page.getByRole("option", { name: "Default - First Name" }).click();
    await page.locator('[id="_qf_Field_next_new-bottom"]').click();

    // add last name field
    await page.locator('[id="field_name[0]"]').selectOption("Individual");
    await page
      .getByRole("textbox", { name: "Default - Individual Prefix" })
      .click();
    await page.getByRole("option", { name: "Default - Last Name" }).click();
    await page.locator('[id="_qf_Field_next-bottom"]').click();

    // verify profile fields are displayed
    await expect(page.locator("#crm-container")).toContainText("First Name");
    await expect(page.locator("#crm-container")).toContainText("Last Name");
  });
  test("Create Contribution Page", async ({ page }) => {
    // go to manage contribution pages
    await page.goto("/civicrm/admin/contribute/add?reset=1&action=add");

    // create new contribution page
    await expect(page.locator("#Settings")).toBeVisible();

    // fill title, content and contribution type
    await page
      .getByRole("checkbox", { name: "Is this Online Contribution" })
      .click();
    await page
      .getByRole("textbox", { name: "Title *" })
      .fill(contribution_page_name);
    await page.getByLabel("Contribution Type *").selectOption("4");

    // go to "Amount" page and configure payment options
    await page.locator('[id="_qf_Settings_upload-bottom"]').click();
    await page.waitForTimeout(2000); // 2 second delay
    await page
      .getByRole("checkbox", { name: "Contribution Amounts section" })
      .uncheck();
    await page
      .getByRole("checkbox", { name: "Execute real-time monetary" })
      .uncheck();

    // enable membership section
    await page.locator('[id="_qf_Amount_upload-bottom"]').click();
    await page
      .getByRole("checkbox", { name: "Membership Section Enabled?" })
      .check();

    // select membership types and require membership signup
    await page
      .getByRole("checkbox", { name: membership_type, exact: true })
      .check();
    await page
      .getByRole("checkbox", { name: "Require Membership Signup" })
      .check();
    await expect(page.locator("#errorList")).not.toBeVisible();

    // configure thank you page
    await page.locator('[id="_qf_MembershipBlock_upload-bottom"]').click();
    await page
      .getByRole("textbox", { name: "Thank-you Page Title *" })
      .fill("thank");
    await expect(
      page.getByRole("textbox", { name: "Thank-you Page Title *" })
    ).toHaveValue("thank");

    // go to "Custom Fields" and select profile
    await page.locator('[id="_qf_ThankYou_upload-bottom"]').click();
    await page.locator('[id="_qf_Contribute_upload-bottom"]').click();
    await page.waitForTimeout(2000); // 2 second delay
    await page.getByLabel("Include Profile(top of page)").selectOption("22");
    await expect(page.getByLabel("Include Profile(top of page)")).toHaveValue(
      "22"
    );

    // complete setup and return to overview
    await page.locator('[id="_qf_Custom_upload-bottom"]').click();
    await page.locator('[id="_qf_Premium_upload-bottom"]').click();
    await page.locator('[id="_qf_Widget_upload-top"]').click();
    await page.locator('[id="_qf_PCP_upload-top"]').click();
    await expect(page).toHaveTitle(
      new RegExp(`Dashlets - ${contribution_page_name}`)
    );
  });
});
