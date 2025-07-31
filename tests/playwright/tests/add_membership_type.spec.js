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
    await test.step("Navigate to add organization page", async () => {
      await page.goto("civicrm/contact/add?reset=1&ct=Organization");
      await utils.wait(wait_secs);
      await expect(page.locator("#Contact")).toBeVisible();
    });

    await test.step("Fill organization name and save", async () => {
      await page.getByLabel("Organization Name").fill(organization);
      await page
        .locator("form[name=Contact] input[type=submit][value='Save']")
        .first()
        .click();
      await utils.wait(wait_secs);
      /* Verify page title contains organization name */
      await expect(page).toHaveTitle(new RegExp("^" + organization));
    });
  });

  test("Create Membership Type", async () => {
    await test.step("Navigate to add membership type page", async () => {
      await page.goto("civicrm/admin/member/membershipType?action=add&reset=1");
      await utils.wait(wait_secs);
      /* Verify membership type form is visible */
      await expect(page.locator("#MembershipType")).toBeVisible();
    });

    await test.step("Fill membership type name, organization name and search", async () => {
      await page.getByLabel("Name\n     *").fill(membership_type);
      await page.getByLabel("Membership Organization").fill(organization);
      /* Search for organization in database */
      await page.locator("#_qf_MembershipType_refresh").click();
      await utils.wait(wait_secs);
      /* Verify organization found in search results */
      await expect(
        page.locator("table tbody tr").filter({ hasText: organization })
      ).toBeVisible();
    });

    await test.step("Configure membership settings and save", async () => {
      await page
        .getByRole("combobox", { name: "Contribution Type" })
        .selectOption("2");
      await page.locator("#duration_interval").fill("1");
      await page
        .getByRole("combobox", { name: "Duration" })
        .selectOption("year");
      await page
        .getByRole("combobox", { name: "Period Type" })
        .selectOption("rolling");
      await page.locator('[id="_qf_MembershipType_upload-bottom"]').click();
      /* Verify membership type appears in results table */
      await expect(
        page.getByRole("cell", { name: membership_type })
      ).toHaveText(membership_type);
    });
  });
  test("Create Membership", async () => {
    const firstName = "test_firstName";
    const lastName = "test_lastName";
    const fullName = firstName + " " + lastName;

    await test.step("Navigate to add membership page", async () => {
      await page.goto(
        "/civicrm/member/add?reset=1&action=add&context=standalone"
      );
      /* Verify page title is correct */
      await expect(page).toHaveTitle(/New Member/);
    });

    await test.step("Create individual contact", async () => {
      await page.locator("#profiles_1").selectOption("4");
      /* Verify dialog input box exists */
      await expect(page.getByRole("dialog")).toBeVisible();
    });

    await test.step("Fill name and submit", async () => {
      await page.locator("#first_name").fill(firstName);
      await page.locator("#last_name").fill(lastName);
      await page.locator("#_qf_Edit_next").click();
      /* Verify data filled correctly */
      await expect(page.locator("#contact_1")).toHaveValue(fullName);
    });

    await test.step("Select organization, membership type, dates and save", async () => {
      element = page.locator('[id="membership_type_id\\[0\\]"]');
      await element.selectOption(organization);
      element = page.locator('[id="membership_type_id\\[1\\]"]');
      await element.selectOption(membership_type);

      /* Set join and start dates */
      await page.locator("#join_date").click();
      await page.getByRole("link", { name: "1", exact: true }).click();
      await page.locator("#start_date").click();
      await page.getByRole("link", { name: "1", exact: true }).click();

      await page.locator('[id="_qf_Membership_upload-bottom"]').click();

      /* Verify page title and table content contain member name */
      await expect(page).toHaveTitle(fullName + " | netiCRM");
      await expect(page.locator("#option11>tbody")).toContainText(
        membership_type
      );
    });
  });
  test("Create Profile", async () => {
    await test.step("Navigate to profile page", async () => {
      await page.goto("/civicrm/admin/uf/group?reset=1");
      /* Verify page title is correct */
      await expect(page).toHaveTitle(/CiviCRM Profile/);
    });

    await test.step("Create new profile", async () => {
      await page.locator("#newCiviCRMProfile-top").click();
      /* Verify form exists */
      await expect(page.locator("#Group")).toBeVisible();
    });

    await test.step("Fill profile", async () => {
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
      await utils.wait(wait_secs);
      await expect(page).toHaveTitle(
        new RegExp(profile_name + " - CiviCRM Profile Fields")
      );
    });

    await test.step("Add  Name field and verify", async () => {
      await page.locator('[id="field_name[0]"]').selectOption("Individual");
      await page
        .getByRole("textbox", { name: "Default - Individual Prefix" })
        .click();
      await page.getByRole("option", { name: "Default - First Name" }).click();
      await page.locator('[id="_qf_Field_next_new-bottom"]').click();
      await page.locator('[id="field_name[0]"]').selectOption("Individual");
      await page
        .getByRole("textbox", { name: "Default - Individual Prefix" })
        .click();
      await page.getByRole("option", { name: "Default - Last Name" }).click();
      await page.locator('[id="_qf_Field_next-bottom"]').click();
      /* Verify profile current fields are displayed */
      await expect(page.locator("#crm-container")).toContainText("First Name");
      await expect(page.locator("#crm-container")).toContainText("Last Name");
    });
  });
  test("Create Contribution Page", async () => {
    await test.step("Navigate to manage contribution pages", async () => {
      await page.goto("/civicrm/admin/contribute/add?reset=1&action=add");
      /* Verify page title is correct */
      await expect(page).toHaveTitle(/Title and Settings/);
    });

    await test.step("Edit contribution page title and settings", async () => {
      await page
        .getByRole("checkbox", { name: "Is this Online Contribution" })
        .click();
      await page
        .getByRole("textbox", { name: "Title *" })
        .fill(contribution_page_name);
      await page.getByLabel("Contribution Type *").selectOption("4");
      /* Verify data filled correctly */
      await expect(page.getByRole("textbox", { name: "Title *" })).toHaveValue(
        contribution_page_name
      );
    });

    await test.step("Configure amount settings", async () => {
      await page.waitForLoadState("networkidle");
      await page.locator('[id="_qf_Settings_upload-bottom"]').click();
      await page.waitForLoadState("networkidle");

      await page
        .getByRole("checkbox", { name: "Contribution Amounts section" })
        .uncheck();
      await page
        .getByRole("checkbox", { name: "Execute real-time monetary" })
        .check();
      await page.getByRole("checkbox", { name: "Pay later option" }).check();
      await page
        .getByRole("textbox", { name: "Pay later instructions" })
        .fill("I will send payment by check");
      /* Verify settings are correct */
      await expect(
        page.getByRole("checkbox", { name: "Pay later option" })
      ).toBeChecked();
    });

    await test.step("Enable membership section", async () => {
      await page.locator('[id="_qf_Amount_upload-bottom"]').click();
      await page
        .getByRole("checkbox", { name: "Membership Section Enabled?" })
        .check();
      /* Verify memberFields element display is block */
      await expect(page.locator("#memberFields")).toHaveCSS("display", "block");
    });

    await test.step("Select membership type and require membership", async () => {
      await page
        .getByRole("checkbox", {
          name: membership_type,
          exact: true,
        })
        .check();
      await page
        .getByRole("checkbox", { name: "Require Membership Signup" })
        .check();
      /* Verify error message does not exist */
      await expect(page.locator("#errorList")).not.toBeVisible();
      await page.locator('[id="_qf_MembershipBlock_upload-bottom"]').click();
    });

    await test.step("Configure thank you page", async () => {
      await page
        .getByRole("textbox", { name: "Thank-you Page Title *" })
        .fill("thank");

      /* Verify data filled correctly */
      await expect(
        page.getByRole("textbox", { name: "Thank-you Page Title *" })
      ).toHaveValue("thank");
      await page.locator('[id="_qf_ThankYou_upload-bottom"]').click();
      await page.locator('[id="_qf_Contribute_upload-bottom"]').click();
    });

    await test.step("Select profile for embedded form", async () => {
      await page.waitForTimeout(1000);
      const options = await page
        .getByLabel("Include Profile(top of page)")
        .locator("option")
        .all();
      for (let i = 0; i < options.length; i++) {
        const text = await options[i].textContent();
        if (text && text.includes(profile_name)) {
          await page
            .getByLabel("Include Profile(top of page)")
            .selectOption({ index: i });
          break;
        }
      }
      /* Verify selection is correct */
      await expect(page.getByLabel("Include Profile(top of page)")).toHaveValue(
        /.+/
      );
    });

    await test.step("Complete remaining setup steps", async () => {
      const steps = ["Custom", "Premium", "Widget", "PCP"];
      for (const step of steps) {
        await page
          .locator(
            `[id="_qf_${step}_upload-${
              step === "Widget" || step === "PCP" ? "top" : "bottom"
            }"]`
          )
          .click();
      }
      /* Verify return to contribution page overview */
      await expect(page).toHaveTitle(
        new RegExp(`Dashlets - ${contribution_page_name}`)
      );
    });
  });
  test("Fill Contribution Form", async () => {
    let contributionPage;

    await test.step("Navigate to contribution page overview", async () => {
      await page.goto("/civicrm/admin/contribute?reset=1");
      await page.locator("#title").fill(contribution_page_name);
      await page.getByRole("button", { name: "Search" }).click();
      await page.getByRole("link", { name: contribution_page_name }).click();

      [contributionPage] = await Promise.all([
        page.waitForEvent("popup"),
        page.getByRole("link", { name: "» Go to this LIVE Online" }).click(),
      ]);

      /* Verify page title is correct */
      await expect(contributionPage).toHaveTitle(
        new RegExp(contribution_page_name)
      );
    });

    await test.step("Select membership type", async () => {
      /* Verify membership type selection is correct */
      await expect(
        contributionPage.getByRole("radio", { name: membership_type })
      ).toBeVisible();
    });

    await test.step("Fill name information", async () => {
      const randomUsername = utils.makeid(8);
      const randomEmail = utils.makeid(8) + "@example.com";

      const email = contributionPage.getByRole("textbox", {
        name: "Email Address *",
      });
      /* Check if user is logged in */
      if ((await email.isVisible()) && (await email.isEditable())) {
        await contributionPage
          .getByRole("textbox", { name: "Username *" })
          .fill(randomUsername);
        await email.fill(randomEmail);
        await contributionPage
          .getByRole("textbox", { name: "First Name" })
          .fill("chenyy");
        await contributionPage
          .getByRole("textbox", { name: "Last Name" })
          .fill("jerryyy");
      }
      /* Verify data filled correctly - only check name if not logged in */
      const firstNameField = contributionPage.getByRole("textbox", {
        name: "First Name",
      });
      if ((await email.isVisible()) && (await email.isEditable())) {
        await expect(firstNameField).toHaveValue("chenyy");
      }
    });

    await test.step("Check input data correctness", async () => {
      await contributionPage.getByRole("button", { name: "Next >>" }).click();
      /* Verify message reminds user transaction is not complete */
      await expect(
        contributionPage.getByText(
          "To complete this transaction, click the Continue button below"
        )
      ).toBeVisible();
    });

    await test.step("Final contribution data", async () => {
      await contributionPage
        .getByRole("button", { name: "Continue >>" })
        .click();
      /* Verify #help container exists and is visible, with payment incomplete message */
      await expect(contributionPage.locator("#help")).toBeVisible();
      await expect(
        contributionPage.getByText(
          "Keep supporting it. Payment has not been completed yet with entire process."
        )
      ).toBeVisible();
    });
  });
  test("Update Membership Status", async () => {
    await test.step("Navigate to contact search page", async () => {
      await page.goto("/civicrm/contact/search?reset=1");
      /* Verify page title is correct */
      await expect(page).toHaveTitle(/Find Contacts/);
    });

    await test.step("Search for contact", async () => {
      await page.getByLabel("Name, Phone or Email").fill("firstName");
      await page.getByRole("button", { name: "Search" }).click();
      /* Verify search-status container exists with results */
      await expect(page.locator("#search-status")).toBeVisible();
    });

    await test.step("Navigate to member details and membership section", async () => {
      await page
        .getByRole("link", { name: "test_firstName test_lastName" })
        .first()
        .click();
      await page.getByRole("link", { name: "Memberships" }).click();
      /* Verify URL contains selectedChild=member query parameter */
      await expect(page).toHaveURL(/selectedChild=member/);
    });

    await test.step("Edit membership status", async () => {
      await page.getByRole("link", { name: "Edit", exact: true }).click();
      await page.getByRole("checkbox", { name: "Status Override?" }).check();
      await page.getByLabel("Membership Status").selectOption("2");
      await page.locator('[id="_qf_Membership_upload-bottom"]').click();
      /* Verify membership status changed from pending/disabled to current */
      await expect(page.locator(".crm-membership-status")).toContainText(
        "Current"
      );
    });

    await test.step("Verify membership dates", async () => {
      const startDateText = await page
        .locator(".crm-membership-start_date")
        .textContent();
      const endDateText = await page
        .locator(".crm-membership-end_date")
        .textContent();

      const startDate = new Date(startDateText.trim());
      const endDate = new Date(endDateText.trim());

      const expectedEndDate = new Date(startDate);
      expectedEndDate.setFullYear(startDate.getFullYear() + 1);
      expectedEndDate.setDate(expectedEndDate.getDate() - 1);

      /* Verify end date is exactly one year from start date */
      expect(endDate.toDateString()).toBe(expectedEndDate.toDateString());
    });
  });
});
