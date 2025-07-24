import { test, expect } from "@playwright/test";

test("可以輸入todo事項並標記完成", async ({ page }) => {
  // 進入網站
  await page.goto(
    "http://localhost:8080/civicrm/admin/member/membershipType?q=&action=add&reset=1"
  );

  // 預期結果: 頁面正常載入，輸入框可見
  await expect(
    page.getByRole("textbox", { name: "What needs to be done?" })
  ).toBeVisible();

  // 輸入事項
  await page
    .getByRole("textbox", { name: "What needs to be done?" })
    .fill("買牛奶");
  await page
    .getByRole("textbox", { name: "What needs to be done?" })
    .press("Enter");

  // 預期結果：事項出現在清單中
  await expect(page.locator(".todo-list li")).toHaveCount(1);
  await expect(page.locator(".todo-list li label")).toContainText("買牛奶");

  // 勾選完成
  await page.getByRole("checkbox", { name: "Toggle Todo" }).check();

  // 預期結果：事項被標記為完成
  await expect(
    page.getByRole("checkbox", { name: "Toggle Todo" })
  ).toBeChecked();
});
