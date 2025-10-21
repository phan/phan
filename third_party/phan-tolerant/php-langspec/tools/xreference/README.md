This tool tries to reconstruct the internal cross references that existed
in the Word document that was used to originally create the draft spec.

If we are lucky, this will only need to be run once as we convert from .docx
to .md.

We first manually grab all of the section numbers from the Word file and
paste them into a CSV-aware file (like an Excel file). Now we have a
column of section numbers

Then we run xreference.php. This does the following:

1. Goes through our Table of Contents and maps, in line order, the current
GitHub link anchor to the section number in our CSV file. This is fragile
because it assumes that the section numbers in the CSV file map 1:1 in
location to that in the ToC.

2. Then it uses our final CSV mapping to replace the Word section numbers
with the numbers and GitHub anchor links.

§11.7.5 => §11_7_5(#the-return-statement)

3. Optionally, the numbers text can be changed to a constant character string

§11.7.5 => §§(#the-return-statement)
