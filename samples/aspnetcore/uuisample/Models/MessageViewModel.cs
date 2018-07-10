namespace uuisample.Models
{
    public class MessageViewModel
    {
        public bool Dismissable { get; set; } = true;
        public string Theme { get; set; } = "success";
        public string Heading { get; set; }
        public string Text { get; set; }
        public string HtmlText { get; set; }
        public string Footer { get; set; }
        public string Xml { get; set; }
    }
}
