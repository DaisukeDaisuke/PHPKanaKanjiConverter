## Mandatory Rules

* Read this file at the start of every chat.
 All file writes must use `apply_patch`.
* Never use command-based replacement. Use partial edits only.
* Do not delete existing comments.
* Do not read large files without a clear reason.
* Limit searches to the smallest relevant path.
* If you're unsure whether a file is worth reading, read only the first 100~280 lines and then decide whether you should read further.
* When using `apply_patch`, you should avoid deleting all lines and then rewriting the entire file with the same content whenever possible. This does not mean you should not replace the file.
* If you write Japanese with` apply_patch`, the characters will not be garbled. Garbled characters are a Powershell problem. If the characters are garbled, we will notify you so you can complete the task with confidence.
* Don't translate everything into English just because the output of Get-Content is garbled. As long as you use apply_patch, you won't have garbled characters.
* After passing phpstan and adding the knowledge file, do not run git diff or re-check the added file. This is a waste of paid credit.
    * If you forget what you added, instead of rereading the file before final submission, it's perfectly fine to honestly say "I forgot" even though you've finished the implementation.
    * However, permission is granted if the purpose is to identify areas for improvement
* To avoid garbled Japanese characters, please use the following commands or methods
    * **You only need to do this on Windows! Use a different command on Mac!**
* [Console]::InputEncoding = [Console]::OutputEncoding = [System.Text.Encoding]::UTF8; Get-Content -Encoding UTF8 file.txt
* [Console]::InputEncoding = [Console]::OutputEncoding = [System.Text.Encoding]::UTF8; $i=1; Get-Content -Encoding UTF8 file.txt | % { "$i: $_"; $i++ }
* Please avoid creating cyclic objects that are not disposed of, as this can cause significant garbage collection overhead due to cycles. If it is absolutely necessary, have the parent object hold all references and use `WeakReference::create` to create references that may be automatically released.
    * This project avoids circular references as much as possible
      **Do not use $entries === []. Instead, use count($entries) === 0**.
* var_dump can cause phpstan to fail, so delete all instances you find. However, disabled var_dump instances are allowed, so do not perform a full search.
* You don't need to submit me the line numbers you edited. I don't need to reread the file in the final submission just to know the line numbers. Because of Git, the filename alone is sufficient.
* src/dictionary_oss is over 10MB, so don't read it.
* ^Yesterday|^I peeled the skin|^I decided|^I want|^the cat|^I took a bite|^and held it down" src\dictionary_oss -g "dictionary*.txt" -S Do not perform searches like this.

## mcp server

Use these tools if you feel it's necessary. Of course, it's perfectly fine to complete everything using only commands.
In particular, `get_symbol_info` should be superior to a Blue Force-style search.

execute_run_configuration
get_run_configurations
get_file_problems
get_project_dependencies
get_project_modules
create_new_file
find_files_by_glob
find_files_by_name_keyword
get_all_open_file_paths
list_directory_tree
open_file_in_editor
reformat_file
get_file_text_by_path
replace_text_in_file
search_in_files_by_regex
get_symbol_info
rename_refactoring
execute_terminal_command
get_repositories