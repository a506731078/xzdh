import tkinter as tk
from tkinter import filedialog, messagebox, scrolledtext
import subprocess
import os
import sys

class GitUploadGUI:
    def __init__(self, root):
        self.root = root
        self.root.title("GitHub 一键上传工具")
        self.root.geometry("700x550")

        # 标签与输入框
        tk.Label(root, text="选择项目文件夹：").pack(pady=5)
        self.folder_path = tk.StringVar()
        tk.Entry(root, textvariable=self.folder_path, width=70).pack(pady=2)
        tk.Button(root, text="浏览...", command=self.browse_folder).pack(pady=5)

        tk.Label(root, text="Git 用户名：").pack(pady=5)
        self.user_name = tk.StringVar()
        tk.Entry(root, textvariable=self.user_name).pack(pady=2)

        tk.Label(root, text="Git 邮箱：").pack(pady=5)
        self.user_email = tk.StringVar()
        tk.Entry(root, textvariable=self.user_email).pack(pady=2)

        tk.Label(root, text="GitHub 远程仓库 URL（如 https://github.com/用户名/仓库名.git）：").pack(pady=5)
        self.repo_url = tk.StringVar()
        tk.Entry(root, textvariable=self.repo_url, width=70).pack(pady=2)

        tk.Button(root, text="开始上传（Git 推送）", command=self.start_upload, bg="#4CAF50", fg="white", width=25).pack(pady=15)

        # 日志输出区域
        tk.Label(root, text="操作日志：").pack(pady=5)
        self.log_area = scrolledtext.ScrolledText(root, width=90, height=20, font=("Courier New", 10))
        self.log_area.pack(pady=5)

    def browse_folder(self):
        path = filedialog.askdirectory()
        if path:
            self.folder_path.set(path)

    def log(self, msg):
        self.log_area.insert(tk.END, msg + "\n")
        self.log_area.see(tk.END)

    def run_cmd(self, cmd, cwd=None):
        try:
            self.log(f"执行: {' '.join(cmd)}")
            result = subprocess.run(cmd, cwd=cwd, capture_output=True, text=True, encoding="utf-8")
            if result.stdout:
                self.log(result.stdout)
            if result.stderr:
                self.log("错误：" + result.stderr)
            return result.returncode == 0
        except Exception as e:
            self.log(f"执行失败: {e}")
            return False

    def start_upload(self):
        folder = self.folder_path.get()
        if not folder:
            messagebox.showerror("错误", "请先选择项目文件夹！")
            return

        name = self.user_name.get()
        email = self.user_email.get()
        url = self.repo_url.get()

        if not (name and email and url):
            messagebox.showerror("错误", "请填写用户名、邮箱和仓库 URL！")
            return

        # 进入项目目录
        self.log(f"工作目录: {folder}")

        # 1. 初始化 Git（如果已有则跳过）
        if not self.run_cmd(["git", "init"], cwd=folder):
            messagebox.showerror("错误", "git init 失败")
            return

        # 2. 设置用户
        self.run_cmd(["git", "config", "user.name", name], cwd=folder)
        self.run_cmd(["git", "config", "user.email", email], cwd=folder)

        # 3. 添加所有文件
        self.run_cmd(["git", "add", "."], cwd=folder)

        # 4. 首次提交
        self.run_cmd(["git", "commit", "-m", "first commit"], cwd=folder)

        # 5. 添加远程仓库
        self.run_cmd(["git", "remote", "remove", "origin"], cwd=folder)  # 避免重复
        if not self.run_cmd(["git", "remote", "add", "origin", url], cwd=folder):
            messagebox.showerror("错误", "添加远程仓库失败，请检查 URL")
            return

        # 6. 推送到 main
        if not self.run_cmd(["git", "branch", "-M", "main"], cwd=folder):
            pass  # 可能已是 main
        if not self.run_cmd(["git", "push", "-u", "origin", "main"], cwd=folder):
            messagebox.showerror("错误", "推送失败！\n可能是网络被封、需要 VPN / 或者仓库已存在。")
            return

        messagebox.showinfo("成功", "项目已成功推送到 GitHub！")
        self.log("推送完成！")

def main():
    root = tk.Tk()
    app = GitUploadGUI(root)
    root.mainloop()

if __name__ == "__main__":
    main()